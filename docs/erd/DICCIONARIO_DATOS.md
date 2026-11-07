# Diccionario de datos

Esquema derivado de las migraciones, sin registros de usuarios. [Volver al diagrama](../DIAGRAMA_ENTIDAD_RELACION.md). Tipos normalizados por SQLite; no son una exportación de DDL MySQL.

## approval_delegations

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `delegator_user_id` | `integer` | FK | No |
| `delegate_user_id` | `integer` | FK | No |
| `starts_at` | `datetime` |  | No |
| `ends_at` | `datetime` |  | No |
| `reason` | `varchar` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `created_by` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`is_active`).
- INDEX (`delegator_user_id`, `starts_at`, `ends_at`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by` | `users(id)` | RESTRICT | NO ACTION |
| `delegate_user_id` | `users(id)` | CASCADE | NO ACTION |
| `delegator_user_id` | `users(id)` | CASCADE | NO ACTION |

## approval_levels

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `approval_workflow_id` | `integer` | FK | No |
| `level_number` | `integer` |  | No |
| `name` | `varchar` |  | No |
| `approver_type` | `varchar` |  | No |
| `approver_user_id` | `integer` | FK | Sí |
| `approver_role_id` | `integer` | FK | Sí |
| `is_required` | `tinyint(1)` |  | No |
| `allow_delegation` | `tinyint(1)` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`approval_workflow_id`, `level_number`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `approval_workflow_id` | `approval_workflows(id)` | CASCADE | NO ACTION |
| `approver_role_id` | `roles(id)` | SET NULL | NO ACTION |
| `approver_user_id` | `users(id)` | SET NULL | NO ACTION |

## approval_workflows

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_category_id` | `integer` | FK | No |
| `ticket_subcategory_id` | `integer` | FK | Sí |
| `requester_area_id` | `integer` | FK | Sí |
| `minimum_priority_id` | `integer` | FK | Sí |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).
- INDEX (`is_active`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `minimum_priority_id` | `ticket_priorities(id)` | SET NULL | NO ACTION |
| `requester_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `ticket_category_id` | `ticket_categories(id)` | CASCADE | NO ACTION |
| `ticket_subcategory_id` | `ticket_subcategories(id)` | SET NULL | NO ACTION |

## areas

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `type` | `varchar` |  | No |
| `parent_id` | `integer` | FK | Sí |
| `head_user_id` | `integer` | FK | Sí |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).
- INDEX (`type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `head_user_id` | `users(id)` | SET NULL | NO ACTION |
| `parent_id` | `areas(id)` | SET NULL | NO ACTION |

## cache

Origen: [`0001_01_01_000001_create_cache_table.php`](../../database/migrations/0001_01_01_000001_create_cache_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `key` | `varchar` | PK | No |
| `value` | `text` |  | No |
| `expiration` | `integer` |  | No |

Clave primaria: `key`.

- INDEX (`expiration`).

Sin claves foráneas declaradas.

## cache_locks

Origen: [`0001_01_01_000001_create_cache_table.php`](../../database/migrations/0001_01_01_000001_create_cache_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `key` | `varchar` | PK | No |
| `owner` | `varchar` |  | No |
| `expiration` | `integer` |  | No |

Clave primaria: `key`.

- INDEX (`expiration`).

Sin claves foráneas declaradas.

## departments

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `area_id` | `integer` | FK | No |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `head_user_id` | `integer` | FK | Sí |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `area_id` | `areas(id)` | CASCADE | NO ACTION |
| `head_user_id` | `users(id)` | SET NULL | NO ACTION |

## development_access_profiles

Origen: [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `development_request_id` | `integer` | FK | No |
| `name` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `permissions` | `text` |  | No |
| `data_scope` | `text` |  | Sí |
| `includes_sensitive_data` | `tinyint(1)` |  | No |
| `third_party_access` | `tinyint(1)` |  | No |
| `created_by_id` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`development_request_id`, `name`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `development_request_id` | `development_requests(id)` | CASCADE | NO ACTION |

## development_deployment_authorizations

Origen: [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `development_request_id` | `integer` | FK | No |
| `version` | `varchar` |  | No |
| `environment` | `varchar` |  | No |
| `release_notes` | `text` |  | No |
| `deployment_plan` | `text` |  | No |
| `rollback_plan` | `text` |  | No |
| `scheduled_at` | `datetime` |  | Sí |
| `requested_by_id` | `integer` | FK | No |
| `requested_at` | `datetime` |  | No |
| `status` | `varchar` |  | No |
| `decided_by_id` | `integer` | FK | Sí |
| `decided_at` | `datetime` |  | Sí |
| `decision_comment` | `varchar` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`status`).
- INDEX (`development_request_id`, `created_at`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `decided_by_id` | `users(id)` | SET NULL | NO ACTION |
| `development_request_id` | `development_requests(id)` | CASCADE | NO ACTION |
| `requested_by_id` | `users(id)` | RESTRICT | NO ACTION |

## development_requests

Origen: [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK, UK | No |
| `request_type` | `varchar` |  | No |
| `project_name` | `varchar` |  | Sí |
| `business_need` | `text` |  | Sí |
| `objective` | `text` |  | Sí |
| `scope` | `text` |  | Sí |
| `expected_benefits` | `text` |  | Sí |
| `affected_system` | `varchar` |  | Sí |
| `expected_delivery_date` | `date` |  | Sí |
| `technical_restrictions` | `text` |  | Sí |
| `information_classification` | `varchar` |  | No |
| `contains_sensitive_data` | `tinyint(1)` |  | No |
| `sensitive_data_description` | `text` |  | Sí |
| `involves_third_parties` | `tinyint(1)` |  | No |
| `third_party_details` | `text` |  | Sí |
| `access_overview` | `text` |  | Sí |
| `repository_reference` | `varchar` |  | Sí |
| `lifecycle_status` | `varchar` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`information_classification`).
- INDEX (`lifecycle_status`).
- INDEX (`request_type`).
- UNIQUE (`ticket_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## development_requirements

Origen: [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `development_request_id` | `integer` | FK | No |
| `code` | `varchar` |  | No |
| `type` | `varchar` |  | No |
| `title` | `varchar` |  | No |
| `description` | `text` |  | No |
| `acceptance_criteria` | `text` |  | Sí |
| `technical_restrictions` | `text` |  | Sí |
| `priority` | `varchar` |  | No |
| `status` | `varchar` |  | No |
| `sort_order` | `integer` |  | No |
| `created_by_id` | `integer` | FK | No |
| `updated_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`development_request_id`, `code`).
- INDEX (`priority`).
- INDEX (`status`).
- INDEX (`type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `development_request_id` | `development_requests(id)` | CASCADE | NO ACTION |
| `updated_by_id` | `users(id)` | SET NULL | NO ACTION |

## development_security_controls

Origen: [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `development_request_id` | `integer` | FK | No |
| `control_type` | `varchar` |  | No |
| `is_required` | `tinyint(1)` |  | No |
| `requirement_details` | `text` |  | Sí |
| `implementation_notes` | `text` |  | Sí |
| `status` | `varchar` |  | No |
| `verified_by_id` | `integer` | FK | Sí |
| `verified_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`development_request_id`, `control_type`).
- INDEX (`status`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `development_request_id` | `development_requests(id)` | CASCADE | NO ACTION |
| `verified_by_id` | `users(id)` | SET NULL | NO ACTION |

## development_test_evidences

Origen: [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `development_request_id` | `integer` | FK | No |
| `test_type` | `varchar` |  | No |
| `title` | `varchar` |  | No |
| `environment` | `varchar` |  | No |
| `result` | `varchar` |  | No |
| `executed_at` | `datetime` |  | No |
| `executed_by_id` | `integer` | FK | No |
| `notes` | `text` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`result`).
- INDEX (`test_type`).
- INDEX (`development_request_id`, `test_type`, `result`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `development_request_id` | `development_requests(id)` | CASCADE | NO ACTION |
| `executed_by_id` | `users(id)` | RESTRICT | NO ACTION |

## equipment

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `inventory_code` | `varchar` | UK | No |
| `asset_type` | `varchar` |  | No |
| `name` | `varchar` |  | No |
| `brand` | `varchar` |  | Sí |
| `model` | `varchar` |  | Sí |
| `serial_number` | `varchar` | UK | Sí |
| `ip_address` | `varchar` |  | Sí |
| `mac_address` | `varchar` | UK | Sí |
| `hostname` | `varchar` |  | Sí |
| `network_segment` | `varchar` |  | Sí |
| `connectivity_notes` | `text` |  | Sí |
| `processor` | `varchar` |  | Sí |
| `memory_mb` | `integer` |  | Sí |
| `storage_gb` | `numeric` |  | Sí |
| `operating_system` | `varchar` |  | Sí |
| `purchase_date` | `date` |  | Sí |
| `warranty_expires_at` | `date` |  | Sí |
| `acquisition_cost` | `numeric` |  | Sí |
| `additional_specifications` | `text` |  | Sí |
| `area_id` | `integer` | FK | Sí |
| `department_id` | `integer` | FK | Sí |
| `responsible_user_id` | `integer` | FK | Sí |
| `location` | `varchar` |  | No |
| `status` | `varchar` |  | No |
| `condition_notes` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_by_id` | `integer` | FK | No |
| `updated_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`asset_type`).
- INDEX (`hostname`).
- UNIQUE (`inventory_code`).
- INDEX (`is_active`).
- UNIQUE (`mac_address`).
- INDEX (`area_id`, `department_id`).
- UNIQUE (`serial_number`).
- INDEX (`status`).
- INDEX (`asset_type`, `status`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `area_id` | `areas(id)` | SET NULL | NO ACTION |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `department_id` | `departments(id)` | SET NULL | NO ACTION |
| `responsible_user_id` | `users(id)` | SET NULL | NO ACTION |
| `updated_by_id` | `users(id)` | SET NULL | NO ACTION |

## equipment_events

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `equipment_id` | `integer` | FK | No |
| `ticket_id` | `integer` | FK | Sí |
| `event_type` | `varchar` |  | No |
| `title` | `varchar` |  | No |
| `description` | `text` |  | No |
| `performed_at` | `datetime` |  | No |
| `provider` | `varchar` |  | Sí |
| `cost` | `numeric` |  | Sí |
| `downtime_minutes` | `integer` |  | Sí |
| `metadata` | `text` |  | Sí |
| `created_by_id` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`equipment_id`, `performed_at`).
- INDEX (`event_type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `equipment_id` | `equipment(id)` | CASCADE | NO ACTION |
| `ticket_id` | `tickets(id)` | SET NULL | NO ACTION |

## equipment_licenses

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `equipment_id` | `integer` | FK | No |
| `name` | `varchar` |  | No |
| `license_reference` | `varchar` |  | Sí |
| `seats` | `integer` |  | No |
| `expires_at` | `date` |  | Sí |
| `notes` | `text` |  | Sí |
| `created_by_id` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`equipment_id`, `name`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `equipment_id` | `equipment(id)` | CASCADE | NO ACTION |

## equipment_movements

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `equipment_id` | `integer` | FK | No |
| `movement_type` | `varchar` |  | No |
| `from_area_id` | `integer` | FK | Sí |
| `to_area_id` | `integer` | FK | Sí |
| `from_department_id` | `integer` | FK | Sí |
| `to_department_id` | `integer` | FK | Sí |
| `from_responsible_id` | `integer` | FK | Sí |
| `to_responsible_id` | `integer` | FK | Sí |
| `from_location` | `varchar` |  | Sí |
| `to_location` | `varchar` |  | Sí |
| `from_status` | `varchar` |  | Sí |
| `to_status` | `varchar` |  | No |
| `effective_at` | `datetime` |  | No |
| `justification` | `varchar` |  | No |
| `authorized_by_id` | `integer` | FK | No |
| `created_by_id` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`equipment_id`, `effective_at`).
- INDEX (`movement_type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `authorized_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `equipment_id` | `equipment(id)` | CASCADE | NO ACTION |
| `from_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `from_department_id` | `departments(id)` | SET NULL | NO ACTION |
| `from_responsible_id` | `users(id)` | SET NULL | NO ACTION |
| `to_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `to_department_id` | `departments(id)` | SET NULL | NO ACTION |
| `to_responsible_id` | `users(id)` | SET NULL | NO ACTION |

## failed_jobs

Origen: [`0001_01_01_000002_create_jobs_table.php`](../../database/migrations/0001_01_01_000002_create_jobs_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `uuid` | `varchar` | UK | No |
| `connection` | `text` |  | No |
| `queue` | `text` |  | No |
| `payload` | `text` |  | No |
| `exception` | `text` |  | No |
| `failed_at` | `datetime` |  | No |

Clave primaria: `id`.

- UNIQUE (`uuid`).

Sin claves foráneas declaradas.

## form_fields

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_form_id` | `integer` | FK | No |
| `name` | `varchar` |  | No |
| `label` | `varchar` |  | No |
| `type` | `varchar` |  | No |
| `placeholder` | `varchar` |  | Sí |
| `help_text` | `text` |  | Sí |
| `default_value` | `text` |  | Sí |
| `options` | `text` |  | Sí |
| `sort_order` | `integer` |  | No |
| `is_required` | `tinyint(1)` |  | No |
| `is_visible` | `tinyint(1)` |  | No |
| `is_readonly` | `tinyint(1)` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `min_value` | `integer` |  | Sí |
| `max_value` | `integer` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`ticket_form_id`, `name`).
- INDEX (`type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_form_id` | `ticket_forms(id)` | CASCADE | NO ACTION |

## group_user

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `it_group_id` | `integer` | PK, FK | No |
| `user_id` | `integer` | PK, FK | No |
| `membership_role` | `varchar` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `it_group_id`, `user_id`.


| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `it_group_id` | `it_groups(id)` | CASCADE | NO ACTION |
| `user_id` | `users(id)` | CASCADE | NO ACTION |

## impact_levels

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `name` | `varchar` | UK | No |
| `value` | `integer` | UK | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`name`).
- UNIQUE (`value`).

Sin claves foráneas declaradas.

## it_groups

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `area_id` | `integer` | FK | Sí |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `coordinator_user_id` | `integer` | FK | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `area_id` | `areas(id)` | SET NULL | NO ACTION |
| `coordinator_user_id` | `users(id)` | SET NULL | NO ACTION |

## job_batches

Origen: [`0001_01_01_000002_create_jobs_table.php`](../../database/migrations/0001_01_01_000002_create_jobs_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `varchar` | PK | No |
| `name` | `varchar` |  | No |
| `total_jobs` | `integer` |  | No |
| `pending_jobs` | `integer` |  | No |
| `failed_jobs` | `integer` |  | No |
| `failed_job_ids` | `text` |  | No |
| `options` | `text` |  | Sí |
| `cancelled_at` | `integer` |  | Sí |
| `created_at` | `integer` |  | No |
| `finished_at` | `integer` |  | Sí |

Clave primaria: `id`.


Sin claves foráneas declaradas.

## jobs

Origen: [`0001_01_01_000002_create_jobs_table.php`](../../database/migrations/0001_01_01_000002_create_jobs_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `queue` | `varchar` |  | No |
| `payload` | `text` |  | No |
| `attempts` | `integer` |  | No |
| `reserved_at` | `integer` |  | Sí |
| `available_at` | `integer` |  | No |
| `created_at` | `integer` |  | No |

Clave primaria: `id`.

- INDEX (`queue`).

Sin claves foráneas declaradas.

## maintenance_checklist_items

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `maintenance_plan_id` | `integer` | FK | No |
| `label` | `varchar` |  | No |
| `instructions` | `text` |  | Sí |
| `sort_order` | `integer` |  | No |
| `is_required` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.


| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `maintenance_plan_id` | `maintenance_plans(id)` | CASCADE | NO ACTION |

## maintenance_evidences

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `maintenance_order_id` | `integer` | FK | No |
| `uploaded_by_id` | `integer` | FK | No |
| `disk` | `varchar` |  | No |
| `path` | `varchar` |  | No |
| `original_name` | `varchar` |  | No |
| `mime_type` | `varchar` |  | No |
| `size` | `integer` |  | No |
| `sha256` | `varchar` |  | No |
| `description` | `varchar` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`maintenance_order_id`, `created_at`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `maintenance_order_id` | `maintenance_orders(id)` | CASCADE | NO ACTION |
| `uploaded_by_id` | `users(id)` | RESTRICT | NO ACTION |

## maintenance_order_items

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `maintenance_order_id` | `integer` | FK | No |
| `maintenance_checklist_item_id` | `integer` | FK | Sí |
| `label` | `varchar` |  | No |
| `sort_order` | `integer` |  | No |
| `is_required` | `tinyint(1)` |  | No |
| `status` | `varchar` |  | No |
| `observation` | `text` |  | Sí |
| `completed_by_id` | `integer` | FK | Sí |
| `completed_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.


| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `completed_by_id` | `users(id)` | SET NULL | NO ACTION |
| `maintenance_checklist_item_id` | `maintenance_checklist_items(id)` | SET NULL | NO ACTION |
| `maintenance_order_id` | `maintenance_orders(id)` | CASCADE | NO ACTION |

## maintenance_orders

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `number` | `varchar` | UK | No |
| `maintenance_plan_id` | `integer` | FK | No |
| `maintenance_plan_equipment_id` | `integer` | FK | No |
| `equipment_id` | `integer` | FK | No |
| `ticket_id` | `integer` | FK, UK | Sí |
| `scheduled_for` | `date` |  | No |
| `scheduled_start_at` | `datetime` |  | Sí |
| `scheduled_end_at` | `datetime` |  | Sí |
| `status` | `varchar` |  | No |
| `assigned_to_id` | `integer` | FK | Sí |
| `generation_source` | `varchar` |  | No |
| `generated_at` | `datetime` |  | Sí |
| `started_at` | `datetime` |  | Sí |
| `completed_at` | `datetime` |  | Sí |
| `result` | `varchar` |  | Sí |
| `observations` | `text` |  | Sí |
| `equipment_previous_status` | `varchar` |  | Sí |
| `created_by_id` | `integer` | FK | No |
| `completed_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`maintenance_plan_id`, `equipment_id`, `scheduled_for`).
- INDEX (`status`, `scheduled_for`).
- UNIQUE (`number`).
- INDEX (`scheduled_for`).
- INDEX (`status`).
- UNIQUE (`ticket_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `assigned_to_id` | `users(id)` | SET NULL | NO ACTION |
| `completed_by_id` | `users(id)` | SET NULL | NO ACTION |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `equipment_id` | `equipment(id)` | RESTRICT | NO ACTION |
| `maintenance_plan_equipment_id` | `maintenance_plan_equipment(id)` | RESTRICT | NO ACTION |
| `maintenance_plan_id` | `maintenance_plans(id)` | RESTRICT | NO ACTION |
| `ticket_id` | `tickets(id)` | SET NULL | NO ACTION |

## maintenance_plan_equipment

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `maintenance_plan_id` | `integer` | FK | No |
| `equipment_id` | `integer` | FK | No |
| `start_date` | `date` |  | No |
| `next_due_date` | `date` |  | No |
| `last_completed_on` | `date` |  | Sí |
| `notes` | `varchar` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`is_active`).
- INDEX (`next_due_date`).
- UNIQUE (`maintenance_plan_id`, `equipment_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `equipment_id` | `equipment(id)` | RESTRICT | NO ACTION |
| `maintenance_plan_id` | `maintenance_plans(id)` | CASCADE | NO ACTION |

## maintenance_plans

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `description` | `text` |  | No |
| `frequency_months` | `integer` |  | No |
| `lead_days` | `integer` |  | No |
| `duration_minutes` | `integer` |  | No |
| `default_start_time` | `time` |  | Sí |
| `assigned_it_area_id` | `integer` | FK | Sí |
| `assigned_it_group_id` | `integer` | FK | Sí |
| `assigned_user_id` | `integer` | FK | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_by_id` | `integer` | FK | No |
| `updated_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).
- INDEX (`is_active`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `assigned_it_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `assigned_it_group_id` | `it_groups(id)` | SET NULL | NO ACTION |
| `assigned_user_id` | `users(id)` | SET NULL | NO ACTION |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `updated_by_id` | `users(id)` | SET NULL | NO ACTION |

## maintenance_sequences

Origen: [`2026_10_08_000001_create_preventive_maintenance.php`](../../database/migrations/2026_10_08_000001_create_preventive_maintenance.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `year` | `integer` | PK | No |
| `last_number` | `integer` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `year`.


Sin claves foráneas declaradas.

## notifications

Origen: [`2026_10_08_000002_create_operations_notifications_reporting.php`](../../database/migrations/2026_10_08_000002_create_operations_notifications_reporting.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `varchar` | PK | No |
| `type` | `varchar` |  | No |
| `notifiable_type` | `varchar` |  | No |
| `notifiable_id` | `integer` |  | No |
| `data` | `text` |  | No |
| `read_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`notifiable_type`, `notifiable_id`).
- INDEX (`read_at`).

Sin claves foráneas declaradas.

## password_reset_tokens

Origen: [`0001_01_01_000000_create_users_table.php`](../../database/migrations/0001_01_01_000000_create_users_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `email` | `varchar` | PK | No |
| `token` | `varchar` |  | No |
| `created_at` | `datetime` |  | Sí |

Clave primaria: `email`.


Sin claves foráneas declaradas.

## permission_role

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `permission_id` | `integer` | PK, FK | No |
| `role_id` | `integer` | PK, FK | No |

Clave primaria: `permission_id`, `role_id`.


| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `permission_id` | `permissions(id)` | CASCADE | NO ACTION |
| `role_id` | `roles(id)` | CASCADE | NO ACTION |

## permissions

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `module` | `varchar` |  | No |
| `action` | `varchar` |  | No |
| `name` | `varchar` |  | No |
| `slug` | `varchar` | UK | No |
| `description` | `text` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`module`).
- UNIQUE (`slug`).

Sin claves foráneas declaradas.

## priority_matrix_rules

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `impact_level_id` | `integer` | FK | No |
| `urgency_level_id` | `integer` | FK | No |
| `ticket_priority_id` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`impact_level_id`, `urgency_level_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `impact_level_id` | `impact_levels(id)` | CASCADE | NO ACTION |
| `ticket_priority_id` | `ticket_priorities(id)` | CASCADE | NO ACTION |
| `urgency_level_id` | `urgency_levels(id)` | CASCADE | NO ACTION |

## role_user

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `role_id` | `integer` | PK, FK | No |
| `user_id` | `integer` | PK, FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `role_id`, `user_id`.


| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `role_id` | `roles(id)` | CASCADE | NO ACTION |
| `user_id` | `users(id)` | CASCADE | NO ACTION |

## roles

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `name` | `varchar` | UK | No |
| `slug` | `varchar` | UK | No |
| `description` | `text` |  | Sí |
| `is_system` | `tinyint(1)` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`name`).
- UNIQUE (`slug`).

Sin claves foráneas declaradas.

## routing_rules

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_category_id` | `integer` | FK | No |
| `ticket_subcategory_id` | `integer` | FK | Sí |
| `it_area_id` | `integer` | FK | Sí |
| `it_group_id` | `integer` | FK | Sí |
| `assignee_user_id` | `integer` | FK | Sí |
| `name` | `varchar` |  | No |
| `precedence` | `integer` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`is_active`).
- INDEX (`ticket_category_id`, `ticket_subcategory_id`, `precedence`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `assignee_user_id` | `users(id)` | SET NULL | NO ACTION |
| `it_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `it_group_id` | `it_groups(id)` | SET NULL | NO ACTION |
| `ticket_category_id` | `ticket_categories(id)` | CASCADE | NO ACTION |
| `ticket_subcategory_id` | `ticket_subcategories(id)` | SET NULL | NO ACTION |

## security_events

Origen: [`2026_07_30_000002_create_security_events_table.php`](../../database/migrations/2026_07_30_000002_create_security_events_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `user_id` | `integer` | FK | Sí |
| `email` | `varchar` |  | Sí |
| `event` | `varchar` |  | No |
| `ip_address` | `varchar` |  | Sí |
| `user_agent` | `text` |  | Sí |
| `details` | `text` |  | Sí |
| `created_at` | `datetime` |  | No |

Clave primaria: `id`.

- INDEX (`created_at`).
- INDEX (`email`).
- INDEX (`event`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `user_id` | `users(id)` | SET NULL | NO ACTION |

## service_level_targets

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_priority_id` | `integer` | FK | No |
| `ticket_category_id` | `integer` | FK | Sí |
| `ticket_subcategory_id` | `integer` | FK | Sí |
| `response_minutes` | `integer` |  | No |
| `resolution_minutes` | `integer` |  | No |
| `escalation_minutes` | `integer` |  | No |
| `business_hours_only` | `tinyint(1)` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`is_active`).
- INDEX (`ticket_priority_id`, `ticket_category_id`, `ticket_subcategory_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_category_id` | `ticket_categories(id)` | CASCADE | NO ACTION |
| `ticket_priority_id` | `ticket_priorities(id)` | CASCADE | NO ACTION |
| `ticket_subcategory_id` | `ticket_subcategories(id)` | CASCADE | NO ACTION |

## sessions

Origen: [`0001_01_01_000000_create_users_table.php`](../../database/migrations/0001_01_01_000000_create_users_table.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `varchar` | PK | No |
| `user_id` | `integer` |  | Sí |
| `ip_address` | `varchar` |  | Sí |
| `user_agent` | `text` |  | Sí |
| `payload` | `text` |  | No |
| `last_activity` | `integer` |  | No |

Clave primaria: `id`.

- INDEX (`last_activity`).
- INDEX (`user_id`).

Sin claves foráneas declaradas.

## specialties

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `name` | `varchar` | UK | No |
| `type` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`name`).
- INDEX (`type`).

Sin claves foráneas declaradas.

## specialty_user

Origen: [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `specialty_id` | `integer` | PK, FK | No |
| `user_id` | `integer` | PK, FK | No |
| `proficiency_level` | `varchar` |  | No |
| `is_primary` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `specialty_id`, `user_id`.


| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `specialty_id` | `specialties(id)` | CASCADE | NO ACTION |
| `user_id` | `users(id)` | CASCADE | NO ACTION |

## supplies

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `category` | `varchar` |  | No |
| `unit` | `varchar` |  | No |
| `stock_quantity` | `numeric` |  | No |
| `minimum_quantity` | `numeric` |  | No |
| `storage_location` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_by_id` | `integer` | FK | No |
| `updated_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`category`).
- UNIQUE (`code`).
- INDEX (`is_active`).
- INDEX (`is_active`, `category`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `updated_by_id` | `users(id)` | SET NULL | NO ACTION |

## supply_movements

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `supply_id` | `integer` | FK | No |
| `ticket_id` | `integer` | FK | Sí |
| `ticket_supply_usage_id` | `integer` | FK | Sí |
| `movement_type` | `varchar` |  | No |
| `quantity_delta` | `numeric` |  | No |
| `balance_before` | `numeric` |  | No |
| `balance_after` | `numeric` |  | No |
| `justification` | `varchar` |  | No |
| `responsible_id` | `integer` | FK | No |
| `authorized_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`supply_id`, `created_at`).
- INDEX (`movement_type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `authorized_by_id` | `users(id)` | SET NULL | NO ACTION |
| `responsible_id` | `users(id)` | RESTRICT | NO ACTION |
| `supply_id` | `supplies(id)` | RESTRICT | NO ACTION |
| `ticket_id` | `tickets(id)` | SET NULL | NO ACTION |
| `ticket_supply_usage_id` | `ticket_supply_usages(id)` | SET NULL | NO ACTION |

## system_alerts

Origen: [`2026_10_08_000002_create_operations_notifications_reporting.php`](../../database/migrations/2026_10_08_000002_create_operations_notifications_reporting.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `fingerprint` | `varchar` | UK | No |
| `ticket_id` | `integer` | FK | Sí |
| `maintenance_order_id` | `integer` | FK | Sí |
| `type` | `varchar` |  | No |
| `severity` | `varchar` |  | No |
| `title` | `varchar` |  | No |
| `message` | `varchar` |  | No |
| `status` | `varchar` |  | No |
| `due_at` | `datetime` |  | Sí |
| `detected_at` | `datetime` |  | No |
| `resolved_at` | `datetime` |  | Sí |
| `metadata` | `text` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`status`, `severity`, `detected_at`).
- INDEX (`due_at`).
- UNIQUE (`fingerprint`).
- INDEX (`severity`).
- INDEX (`status`).
- INDEX (`type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `maintenance_order_id` | `maintenance_orders(id)` | CASCADE | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_activities

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `user_id` | `integer` | FK | No |
| `activity_date` | `date` |  | No |
| `activity_type` | `varchar` |  | No |
| `minutes_spent` | `integer` |  | No |
| `description` | `text` |  | No |
| `diagnosis` | `text` |  | Sí |
| `progress_percentage` | `integer` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`ticket_id`, `activity_date`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |
| `user_id` | `users(id)` | RESTRICT | NO ACTION |

## ticket_approvals

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `approval_workflow_id` | `integer` | FK | Sí |
| `approval_level_id` | `integer` | FK | Sí |
| `level_number` | `integer` |  | No |
| `level_name` | `varchar` |  | No |
| `approver_type` | `varchar` |  | No |
| `approver_user_id` | `integer` | FK | Sí |
| `approver_role_id` | `integer` | FK | Sí |
| `status` | `varchar` |  | No |
| `decided_by_id` | `integer` | FK | Sí |
| `delegated_for_id` | `integer` | FK | Sí |
| `comment` | `varchar` |  | Sí |
| `decided_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`status`).
- UNIQUE (`ticket_id`, `level_number`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `approval_level_id` | `approval_levels(id)` | SET NULL | NO ACTION |
| `approval_workflow_id` | `approval_workflows(id)` | SET NULL | NO ACTION |
| `approver_role_id` | `roles(id)` | SET NULL | NO ACTION |
| `approver_user_id` | `users(id)` | SET NULL | NO ACTION |
| `decided_by_id` | `users(id)` | SET NULL | NO ACTION |
| `delegated_for_id` | `users(id)` | SET NULL | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_assignments

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `from_area_id` | `integer` | FK | Sí |
| `from_group_id` | `integer` | FK | Sí |
| `from_user_id` | `integer` | FK | Sí |
| `to_area_id` | `integer` | FK | Sí |
| `to_group_id` | `integer` | FK | Sí |
| `to_user_id` | `integer` | FK | Sí |
| `assigned_by_id` | `integer` | FK | No |
| `action` | `varchar` |  | No |
| `status` | `varchar` |  | No |
| `reason` | `varchar` |  | Sí |
| `rejection_reason` | `varchar` |  | Sí |
| `responded_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`ticket_id`, `created_at`).
- INDEX (`status`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `assigned_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `from_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `from_group_id` | `it_groups(id)` | SET NULL | NO ACTION |
| `from_user_id` | `users(id)` | SET NULL | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |
| `to_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `to_group_id` | `it_groups(id)` | SET NULL | NO ACTION |
| `to_user_id` | `users(id)` | SET NULL | NO ACTION |

## ticket_attachments

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php), [`2026_08_19_000001_create_module_five_development.php`](../../database/migrations/2026_08_19_000001_create_module_five_development.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `uploaded_by_id` | `integer` | FK | No |
| `ticket_comment_id` | `integer` | FK | Sí |
| `ticket_activity_id` | `integer` | FK | Sí |
| `context` | `varchar` |  | No |
| `disk` | `varchar` |  | No |
| `path` | `varchar` |  | No |
| `original_name` | `varchar` |  | No |
| `mime_type` | `varchar` |  | No |
| `size` | `integer` |  | No |
| `sha256` | `varchar` |  | No |
| `is_internal` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |
| `development_test_evidence_id` | `integer` | FK | Sí |

Clave primaria: `id`.

- INDEX (`ticket_id`, `context`).
- INDEX (`context`).
- INDEX (`is_internal`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `development_test_evidence_id` | `development_test_evidences(id)` | CASCADE | NO ACTION |
| `ticket_activity_id` | `ticket_activities(id)` | CASCADE | NO ACTION |
| `ticket_comment_id` | `ticket_comments(id)` | CASCADE | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |
| `uploaded_by_id` | `users(id)` | RESTRICT | NO ACTION |

## ticket_categories

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `type` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `sort_order` | `integer` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).
- INDEX (`is_active`).
- INDEX (`type`).

Sin claves foráneas declaradas.

## ticket_comments

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `user_id` | `integer` | FK | No |
| `body` | `text` |  | No |
| `is_internal` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`ticket_id`, `created_at`).
- INDEX (`is_internal`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |
| `user_id` | `users(id)` | RESTRICT | NO ACTION |

## ticket_equipment_links

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php), [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `inventory_code` | `varchar` |  | No |
| `equipment_name` | `varchar` |  | Sí |
| `linked_by_id` | `integer` | FK | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |
| `equipment_id` | `integer` | FK | Sí |

Clave primaria: `id`.

- INDEX (`equipment_id`).
- UNIQUE (`ticket_id`, `inventory_code`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `equipment_id` | `equipment(id)` | SET NULL | NO ACTION |
| `linked_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_escalations

Origen: [`2026_10_08_000002_create_operations_notifications_reporting.php`](../../database/migrations/2026_10_08_000002_create_operations_notifications_reporting.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `level` | `varchar` |  | No |
| `source` | `varchar` |  | No |
| `trigger_key` | `varchar` |  | No |
| `reason` | `varchar` |  | No |
| `escalated_to_user_id` | `integer` | FK | Sí |
| `created_by_id` | `integer` | FK | Sí |
| `escalated_at` | `datetime` |  | No |
| `acknowledged_by_id` | `integer` | FK | Sí |
| `acknowledged_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`level`, `escalated_at`).
- UNIQUE (`ticket_id`, `level`, `trigger_key`).
- INDEX (`level`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `acknowledged_by_id` | `users(id)` | SET NULL | NO ACTION |
| `created_by_id` | `users(id)` | SET NULL | NO ACTION |
| `escalated_to_user_id` | `users(id)` | SET NULL | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_field_values

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `form_field_id` | `integer` | FK | No |
| `value` | `text` |  | Sí |
| `value_json` | `text` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`ticket_id`, `form_field_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `form_field_id` | `form_fields(id)` | RESTRICT | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_forms

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_category_id` | `integer` | FK | No |
| `ticket_subcategory_id` | `integer` | FK | Sí |
| `code` | `varchar` | UK | No |
| `name` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `version` | `integer` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).
- INDEX (`is_active`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_category_id` | `ticket_categories(id)` | CASCADE | NO ACTION |
| `ticket_subcategory_id` | `ticket_subcategories(id)` | SET NULL | NO ACTION |

## ticket_histories

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `user_id` | `integer` | FK | Sí |
| `event` | `varchar` |  | No |
| `description` | `varchar` |  | No |
| `from_values` | `text` |  | Sí |
| `to_values` | `text` |  | Sí |
| `ip_address` | `varchar` |  | Sí |
| `user_agent` | `varchar` |  | Sí |
| `created_at` | `datetime` |  | No |

Clave primaria: `id`.

- INDEX (`ticket_id`, `created_at`).
- INDEX (`event`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |
| `user_id` | `users(id)` | SET NULL | NO ACTION |

## ticket_priorities

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `code` | `varchar` | UK | No |
| `name` | `varchar` | UK | No |
| `color` | `varchar` |  | No |
| `weight` | `integer` | UK | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`code`).
- INDEX (`is_active`).
- UNIQUE (`name`).
- UNIQUE (`weight`).

Sin claves foráneas declaradas.

## ticket_relations

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `related_ticket_id` | `integer` | FK | No |
| `type` | `varchar` |  | No |
| `created_by_id` | `integer` | FK | No |
| `note` | `varchar` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`ticket_id`, `related_ticket_id`, `type`).
- INDEX (`type`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `related_ticket_id` | `tickets(id)` | CASCADE | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_reviews

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK, UK | No |
| `reviewed_by_id` | `integer` | FK | No |
| `decision` | `varchar` |  | No |
| `rating` | `integer` |  | Sí |
| `comment` | `varchar` |  | Sí |
| `confirmed_at` | `datetime` |  | No |
| `confirmation_ip` | `varchar` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`ticket_id`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `reviewed_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_sequences

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `year` | `integer` | PK | No |
| `last_number` | `integer` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `year`.


Sin claves foráneas declaradas.

## ticket_statuses

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `name` | `varchar` | UK | No |
| `slug` | `varchar` | UK | No |
| `color` | `varchar` |  | No |
| `is_initial` | `tinyint(1)` |  | No |
| `is_final` | `tinyint(1)` |  | No |
| `is_active` | `tinyint(1)` |  | No |
| `sort_order` | `integer` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`is_active`).
- UNIQUE (`name`).
- UNIQUE (`slug`).

Sin claves foráneas declaradas.

## ticket_subcategories

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_category_id` | `integer` | FK | No |
| `code` | `varchar` |  | No |
| `name` | `varchar` |  | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `sort_order` | `integer` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`is_active`).
- UNIQUE (`ticket_category_id`, `code`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `ticket_category_id` | `ticket_categories(id)` | CASCADE | NO ACTION |

## ticket_supply_usages

Origen: [`2026_09_03_000001_create_module_six_inventory.php`](../../database/migrations/2026_09_03_000001_create_module_six_inventory.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `supply_id` | `integer` | FK | No |
| `quantity_reserved` | `numeric` |  | No |
| `quantity_consumed` | `numeric` |  | Sí |
| `status` | `varchar` |  | No |
| `notes` | `varchar` |  | Sí |
| `recorded_by_id` | `integer` | FK | No |
| `consumed_by_id` | `integer` | FK | Sí |
| `consumed_at` | `datetime` |  | Sí |
| `released_by_id` | `integer` | FK | Sí |
| `released_at` | `datetime` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`ticket_id`, `supply_id`).
- INDEX (`status`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `consumed_by_id` | `users(id)` | SET NULL | NO ACTION |
| `recorded_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `released_by_id` | `users(id)` | SET NULL | NO ACTION |
| `supply_id` | `supplies(id)` | RESTRICT | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## ticket_third_party_dependencies

Origen: [`2026_10_08_000002_create_operations_notifications_reporting.php`](../../database/migrations/2026_10_08_000002_create_operations_notifications_reporting.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `ticket_id` | `integer` | FK | No |
| `provider` | `varchar` |  | No |
| `reference` | `varchar` |  | Sí |
| `description` | `text` |  | No |
| `status` | `varchar` |  | No |
| `requested_at` | `datetime` |  | No |
| `due_at` | `datetime` |  | Sí |
| `resolved_at` | `datetime` |  | Sí |
| `resolution` | `text` |  | Sí |
| `created_by_id` | `integer` | FK | No |
| `resolved_by_id` | `integer` | FK | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`ticket_id`, `status`).
- INDEX (`due_at`).
- INDEX (`status`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `resolved_by_id` | `users(id)` | SET NULL | NO ACTION |
| `ticket_id` | `tickets(id)` | CASCADE | NO ACTION |

## tickets

Origen: [`2026_08_04_000002_create_module_four_tickets.php`](../../database/migrations/2026_08_04_000002_create_module_four_tickets.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `number` | `varchar` | UK | No |
| `requester_id` | `integer` | FK | No |
| `created_by_id` | `integer` | FK | No |
| `source` | `varchar` |  | No |
| `is_draft` | `tinyint(1)` |  | No |
| `title` | `varchar` |  | Sí |
| `description` | `text` |  | Sí |
| `ticket_category_id` | `integer` | FK | No |
| `ticket_subcategory_id` | `integer` | FK | Sí |
| `ticket_form_id` | `integer` | FK | Sí |
| `requester_area_id` | `integer` | FK | Sí |
| `requester_department_id` | `integer` | FK | Sí |
| `location` | `varchar` |  | Sí |
| `attention_type` | `varchar` |  | Sí |
| `impact_level_id` | `integer` | FK | Sí |
| `urgency_level_id` | `integer` | FK | Sí |
| `ticket_priority_id` | `integer` | FK | Sí |
| `ticket_status_id` | `integer` | FK | Sí |
| `approval_status` | `varchar` |  | No |
| `current_it_area_id` | `integer` | FK | Sí |
| `current_it_group_id` | `integer` | FK | Sí |
| `current_assignee_id` | `integer` | FK | Sí |
| `assignment_status` | `varchar` |  | Sí |
| `submitted_at` | `datetime` |  | Sí |
| `response_due_at` | `datetime` |  | Sí |
| `escalation_due_at` | `datetime` |  | Sí |
| `resolution_due_at` | `datetime` |  | Sí |
| `first_response_at` | `datetime` |  | Sí |
| `solution` | `text` |  | Sí |
| `solution_by_id` | `integer` | FK | Sí |
| `solved_at` | `datetime` |  | Sí |
| `closed_by_id` | `integer` | FK | Sí |
| `closed_at` | `datetime` |  | Sí |
| `cancellation_reason` | `varchar` |  | Sí |
| `cancelled_at` | `datetime` |  | Sí |
| `reopen_count` | `integer` |  | No |
| `reopened_at` | `datetime` |  | Sí |
| `last_activity_at` | `datetime` |  | Sí |
| `lock_version` | `integer` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- INDEX (`current_assignee_id`, `ticket_status_id`).
- INDEX (`ticket_category_id`, `ticket_subcategory_id`).
- INDEX (`current_it_group_id`, `ticket_status_id`).
- INDEX (`requester_id`, `created_at`).
- INDEX (`approval_status`).
- INDEX (`assignment_status`).
- INDEX (`is_draft`).
- UNIQUE (`number`).
- INDEX (`source`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `closed_by_id` | `users(id)` | SET NULL | NO ACTION |
| `created_by_id` | `users(id)` | RESTRICT | NO ACTION |
| `current_assignee_id` | `users(id)` | SET NULL | NO ACTION |
| `current_it_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `current_it_group_id` | `it_groups(id)` | SET NULL | NO ACTION |
| `impact_level_id` | `impact_levels(id)` | SET NULL | NO ACTION |
| `requester_area_id` | `areas(id)` | SET NULL | NO ACTION |
| `requester_department_id` | `departments(id)` | SET NULL | NO ACTION |
| `requester_id` | `users(id)` | RESTRICT | NO ACTION |
| `solution_by_id` | `users(id)` | SET NULL | NO ACTION |
| `ticket_category_id` | `ticket_categories(id)` | RESTRICT | NO ACTION |
| `ticket_form_id` | `ticket_forms(id)` | SET NULL | NO ACTION |
| `ticket_priority_id` | `ticket_priorities(id)` | SET NULL | NO ACTION |
| `ticket_status_id` | `ticket_statuses(id)` | SET NULL | NO ACTION |
| `ticket_subcategory_id` | `ticket_subcategories(id)` | SET NULL | NO ACTION |
| `urgency_level_id` | `urgency_levels(id)` | SET NULL | NO ACTION |

## urgency_levels

Origen: [`2026_08_04_000001_create_module_three_configuration.php`](../../database/migrations/2026_08_04_000001_create_module_three_configuration.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `name` | `varchar` | UK | No |
| `value` | `integer` | UK | No |
| `description` | `text` |  | Sí |
| `is_active` | `tinyint(1)` |  | No |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |

Clave primaria: `id`.

- UNIQUE (`name`).
- UNIQUE (`value`).

Sin claves foráneas declaradas.

## users

Origen: [`0001_01_01_000000_create_users_table.php`](../../database/migrations/0001_01_01_000000_create_users_table.php), [`2026_07_30_000001_add_security_fields_to_users_table.php`](../../database/migrations/2026_07_30_000001_add_security_fields_to_users_table.php), [`2026_07_31_000001_create_module_two_structure.php`](../../database/migrations/2026_07_31_000001_create_module_two_structure.php).

| Columna | Tipo normalizado | Claves | Acepta NULL |
| --- | --- | --- | --- |
| `id` | `integer` | PK | No |
| `name` | `varchar` |  | No |
| `email` | `varchar` | UK | No |
| `email_verified_at` | `datetime` |  | Sí |
| `password` | `varchar` |  | No |
| `remember_token` | `varchar` |  | Sí |
| `created_at` | `datetime` |  | Sí |
| `updated_at` | `datetime` |  | Sí |
| `status` | `varchar` |  | No |
| `is_super_admin` | `tinyint(1)` |  | No |
| `failed_login_attempts` | `integer` |  | No |
| `locked_until` | `datetime` |  | Sí |
| `last_failed_login_at` | `datetime` |  | Sí |
| `last_login_at` | `datetime` |  | Sí |
| `last_login_ip` | `varchar` |  | Sí |
| `password_changed_at` | `datetime` |  | Sí |
| `force_password_change` | `tinyint(1)` |  | No |
| `employee_code` | `varchar` | UK | Sí |
| `job_title` | `varchar` |  | Sí |
| `phone_extension` | `varchar` |  | Sí |
| `area_id` | `integer` | FK | Sí |
| `department_id` | `integer` | FK | Sí |

Clave primaria: `id`.

- UNIQUE (`email`).
- UNIQUE (`employee_code`).

| FK local | Destino | Al eliminar | Al actualizar |
| --- | --- | --- | --- |
| `area_id` | `areas(id)` | SET NULL | NO ACTION |
| `department_id` | `departments(id)` | SET NULL | NO ACTION |
