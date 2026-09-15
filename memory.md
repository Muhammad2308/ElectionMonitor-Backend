# Election Intelligence Platform (EIP) — Master Project Definition

---

## Project Identity

| Field | Value |
|---|---|
| **Project Name** | Election Intelligence Platform (EIP) |
| **Purpose** | Nationwide election monitoring, observer management, incident reporting, GIS intelligence, and situation room platform for Nigeria |
| **Status** | Architecture complete — implementation in progress |
| **Architecture Approved** | Yes — do not redesign |

---

## Technology Stack

### Backend
| Layer | Technology | Version |
|---|---|---|
| Framework | Laravel | 12 (target; currently 11) |
| Language | PHP | 8.4+ |
| Database | MySQL | Production target (SQLite in dev) |
| Authentication | Laravel Sanctum | 4.x |
| RBAC | Spatie Laravel-Permission | 6.x |

### Frontend
| Layer | Technology | Version |
|---|---|---|
| Framework | React | 19.x |
| Language | TypeScript | 6.x |
| Build Tool | Vite | 8.x |
| State Management | Zustand | 5.x |
| Data Fetching | TanStack React Query | 5.x |
| HTTP Client | Axios | 1.x |
| Offline Storage | Dexie (IndexedDB) | 4.x |
| Maps | Leaflet + GeoJSON | — |
| PWA | vite-plugin-pwa | — |

---

## Project Structure

```
EllectionsInteligencePlatform/
├── EIP Backend/                        (Laravel API)
│   └── app/Modules/
│       ├── Authentication/             ✓ Built
│       ├── ReferenceData/              ✓ Built (missing models)
│       ├── Incidents/                  ✓ Built (missing IncidentMedia model)
│       ├── Users/                      ✗ Empty
│       ├── Roles/                      ✗ Empty
│       ├── Observers/                  ✗ Empty
│       ├── Assignments/                ✗ Empty
│       ├── GIS/                        ✗ Empty
│       ├── Notifications/              ✗ Empty
│       ├── Reports/                    ✗ Empty
│       ├── Audit/                      ✗ Empty
│       └── Synchronization/            ✗ Empty
│
├── EIP PWA/                            (React PWA)
│   └── src/features/
│       ├── Auth/                       ✓ Built
│       ├── Dashboard/                  ✓ Built
│       ├── Incidents/                  ✓ Built
│       ├── Tracking/                   ✓ Built
│       ├── Assignments/                ~ Partial (local DB only)
│       └── Geography/                  ✓ Built
│
└── states-and-lgas-and-wards-and-polling-units.json   (Master data — 3.5MB)
```

---

## Master Data

A complete Nigerian electoral hierarchy dataset exists at the project root:

```
states-and-lgas-and-wards-and-polling-units.json
```

**Hierarchy:** State → LGA → Ward → Polling Unit

- Polling Units contain GPS coordinates (latitude, longitude)
- This is the backbone of all operational data
- All incidents, assignments, and check-ins reference `polling_unit_id`
- **Never use free-text polling unit names**

---

## Database Schema

### Electoral Geography
```
states         id, name, iso_code
lgas           id, state_id, name
wards          id, lga_id, name
polling_units  id, ward_id, pu_code, name, latitude, longitude
```

### Users & Auth
```
users                   id, name, email, password, state_id, device_id, phone, status, last_login_at
personal_access_tokens  (Sanctum standard)
roles / permissions     (Spatie standard)
```

### Operational
```
incident_categories     id, name, description
observer_assignments    id, user_id, polling_unit_id, election_date
incidents               id (UUID), user_id, polling_unit_id, category_id, description,
                        incident_time, latitude, longitude, sync_status (pending|synced|conflict)
incident_media          id (UUID), incident_id, media_type, file_path, file_hash
observer_check_ins      id, user_id, polling_unit_id, check_in_time, latitude, longitude, distance_from_pu
gps_locations           id, user_id, latitude, longitude, battery_level, captured_at
```

### System
```
activity_logs    id, user_id, action, entity_name, entity_id, old_values, new_values, ip_address
system_settings  id, key (unique), value, description
sync_queue       id, user_id, entity_type, entity_uuid, payload, retry_count, last_error, status
```

---

## User Roles (RBAC)

| Role | Scope |
|---|---|
| Super Administrator | Full system access |
| National Administrator | All states |
| State Coordinator | Single state |
| LGA Supervisor | Single LGA |
| Ward Supervisor | Single ward |
| Observer | Own data only |

RBAC implemented via Spatie Laravel-Permission. Tables migrated. **Roles/permissions not yet seeded or enforced.**

---

## Core Business Flow

```
Observer → Assignment → Polling Unit → Check-In → Monitoring → Incident Reporting → Situation Room → Resolution
```

---

## API Endpoints (Current)

### Public
```
POST  /api/auth/login
GET   /api/geography/states
```

### Protected (Sanctum token required)
```
POST  /api/auth/logout
GET   /api/geography/lgas
GET   /api/geography/wards
GET   /api/geography/polling-units
POST  /api/incidents/report
POST  /api/incidents/media
POST  /api/observers/check-in       (route exists, no implementation)
POST  /api/observers/location       (route exists, no implementation)
```

> **Note:** Frontend calls `/api/v1/...` but backend routes are at `/api/...` — prefix mismatch must be resolved.

---

## Frontend Architecture

### PWA Offline Storage (Dexie / IndexedDB)
```
states, lgas, wards, polling_units   (reference data synced from backend)
incidents                             (user-created, sync_status: pending|synced|conflict)
incident_media                        (blobs)
queued_actions                        (offline sync queue)
```

### Sync Manager
- Runs every 15 seconds when online
- Processes: `CREATE_INCIDENT`, `UPLOAD_MEDIA`, `CHECK_IN`, `LOCATION_UPDATE`
- Retry backoff: 30s → 2m → 10m → 1h
- Conflict detection: HTTP 409 → marks as conflict

### Zustand Stores
- `useAuthStore` — user, token, isAuthenticated (persisted to localStorage: `eip-auth-storage`)
- `useSyncStore` — online status, pending/failed counts, last sync time

### Authentication Flow
1. Login form → email + password + device_id
2. Backend returns Sanctum token (30-day expiry, device ID bound)
3. Frontend stores token in Zustand (persisted)
4. All requests: `Authorization: Bearer <token>` + `X-Device-ID: <uuid>`
5. `DeviceBindingMiddleware` validates device ID matches token

---

## Architectural Patterns (Approved)

### Backend
- **Service Layer** — business logic in `Services/` classes
- **Repository Pattern** — where beneficial for complex queries
- **Form Requests** — all input validation
- **API Resources** — all responses
- **Policies** — all authorization
- **Database Transactions** — all multi-step writes
- **Modular structure** — all code under `app/Modules/<ModuleName>/`

### Frontend
- **Feature-based structure** — `src/features/<FeatureName>/`
- **Offline-first** — write to Dexie first, sync later
- **TypeScript** — strict typing throughout
- **Reusable components** — shared UI in `src/components/`

---

## GIS Requirements

| Capability | Status |
|---|---|
| Polling Unit layer (GeoJSON) | Not built |
| Observer layer | Not built |
| Incident layer | Not built |
| Marker clustering | Not built |
| Distance validation (500m check-in radius) | Built (frontend Haversine) |
| GPS verification | Built (backend stores lat/lng) |
| Heatmap readiness | Not built |

---

## Offline / PWA Requirements

| Capability | Status |
|---|---|
| Offline check-in | Built (queued) |
| Offline incident submission | Built (Dexie) |
| Offline media capture | Built (blob in Dexie) |
| Queued synchronization | Built (SyncManager) |
| Conflict resolution | Detection built; UI not built |
| Retry logic | Built (exponential backoff) |
| Device registration | Built (device_id fingerprint) |
| Service worker / manifest | **NOT configured** — vite-plugin-pwa not wired |

---

## Security Requirements

| Requirement | Status |
|---|---|
| Sanctum token auth | Done |
| Device binding middleware | Done |
| RBAC (roles/permissions) | Tables ready; **not enforced** |
| GPS validation | Partial (distance check in frontend) |
| Media validation | Not built |
| Rate limiting | Not built |
| Audit trails (activity_logs table) | Table exists; not written to |
| Account suspension | Column exists (status); not enforced |
| IP logging | Not built |

---

## Reporting Requirements (Not Built)

- National / State / LGA / Ward / Polling Unit reports
- Observer performance reports
- Incident statistics
- GIS analytics
- PDF exports
- Excel exports

---

## Known Issues & Architectural Gaps

| Issue | Severity |
|---|---|
| Missing Eloquent models: State, LGA, Ward, PollingUnit, IncidentMedia | Critical |
| User model missing fillable fields, HasRoles trait, relationships | Critical |
| RBAC not wired — no permissions enforced on any endpoint | Critical |
| Observer check-in endpoint has no implementation | Critical |
| GPS location endpoint has no implementation | Critical |
| PWA service worker not configured | Critical |
| API route prefix mismatch (`/api` vs `/api/v1`) | High |
| Laravel 11 in use; spec requires Laravel 12 | Medium |
| SQLite in use; production target is MySQL | High |
| No Form Requests on existing endpoints | Medium |
| No API Resources on incident/auth responses | Medium |
| No Policies on any endpoint | High |
| No admin interface of any kind | High |

---

## Implementation Milestones

### Milestone 1 — "System Operable"
**Goal:** Core field workflow fully functional; system administrable.

- [ ] Create all missing Eloquent models
- [ ] Fix User model (fillable, HasRoles, relationships)
- [ ] Define and seed all 6 roles + permissions
- [ ] Enforce RBAC gates on all endpoints
- [ ] Complete observer check-in (controller + service + validation)
- [ ] Complete GPS location tracking
- [ ] Build Users module (CRUD, suspend, role assign)
- [ ] Build Observers module (registration, profile, performance)
- [ ] Build Assignments module (assign, bulk assign, view)
- [ ] Add Form Requests to all endpoints
- [ ] Add API Resources to all responses
- [ ] Add Policies to all resource endpoints
- [ ] Fix API route prefix consistency
- [ ] Configure PWA (vite-plugin-pwa, manifest, service worker)

### Milestone 2 — "Situation Room Live"
- [ ] GIS module backend (GeoJSON endpoints, spatial queries)
- [ ] GIS frontend (Leaflet map, PU/observer/incident layers)
- [ ] Reports module (national → ward hierarchy)
- [ ] Situation Room dashboard frontend
- [ ] PDF + Excel export

### Milestone 3 — "Production Ready"
- [ ] Upgrade Laravel 11 → 12
- [ ] Switch to MySQL; test all migrations + seeders
- [ ] Notifications module (incident escalation, push)
- [ ] Audit module (expose activity_log endpoints)
- [ ] Rate limiting per route
- [ ] Media validation (type, size)
- [ ] Conflict resolution UI
- [ ] Offline fallback page

---

## Key File Locations

### Backend
| File | Path |
|---|---|
| API routes | `EIP Backend/routes/api.php` |
| Auth controller | `EIP Backend/app/Modules/Authentication/Controllers/AuthController.php` |
| Token service | `EIP Backend/app/Modules/Authentication/Services/TokenService.php` |
| Device middleware | `EIP Backend/app/Modules/Authentication/Middleware/DeviceBindingMiddleware.php` |
| Geography controller | `EIP Backend/app/Modules/ReferenceData/Controllers/GeographyController.php` |
| PollingUnit resource | `EIP Backend/app/Modules/ReferenceData/Resources/PollingUnitResource.php` |
| Incident controller | `EIP Backend/app/Modules/Incidents/Controllers/IncidentController.php` |
| Incident model | `EIP Backend/app/Modules/Incidents/Models/Incident.php` |
| Incident service | `EIP Backend/app/Modules/Incidents/Services/IncidentReportingService.php` |
| Hierarchy seeder | `EIP Backend/database/seeders/ElectoralHierarchySeeder.php` |
| Category seeder | `EIP Backend/database/seeders/CategorySeeder.php` |
| Master data JSON | `states-and-lgas-and-wards-and-polling-units.json` (project root) |

### Frontend
| File | Path |
|---|---|
| App router | `EIP PWA/src/App.tsx` |
| Axios client | `EIP PWA/src/api/index.ts` |
| Sync manager | `EIP PWA/src/api/SyncManager.ts` |
| Dexie schema | `EIP PWA/src/storage/db.ts` |
| Auth store | `EIP PWA/src/store/useAuthStore.ts` |
| Sync store | `EIP PWA/src/store/useSyncStore.ts` |
| Login | `EIP PWA/src/features/Auth/Login.tsx` |
| Dashboard | `EIP PWA/src/features/Dashboard/Dashboard.tsx` |
| Incident form | `EIP PWA/src/features/Incidents/IncidentForm.tsx` |
| Media capture | `EIP PWA/src/features/Incidents/MediaCapture.tsx` |
| Check-in | `EIP PWA/src/features/Tracking/CheckIn.tsx` |
| Assignment view | `EIP PWA/src/features/Assignments/AssignmentDetails.tsx` |
| Geolocation hook | `EIP PWA/src/hooks/useGeolocation.ts` |
| Device identity | `EIP PWA/src/utils/deviceIdentity.ts` |
| PWA config | `EIP PWA/vite.config.ts` (needs plugin wired) |

---

## Incident Categories (Seeded)

1. Violence
2. Vote Buying
3. Ballot Snatching
4. Missing Materials
5. Intimidation
6. Delayed Opening
7. Result Manipulation
8. Other

---

*Last updated: 2026-06-15*
*Assessment produced by: Claude Code (claude-sonnet-4-6)*
