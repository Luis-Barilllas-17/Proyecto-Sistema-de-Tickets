# Diagrama entidad–relación resumido — ZOLIC

Un único diagrama con las **12 entidades principales** y **13 relaciones esenciales** del sistema.

[Abrir diagrama](DIAGRAMA_ENTIDAD_RELACION.html) · [Imagen SVG](erd/resumen.svg)

Se omiten tablas auxiliares, auditoría y campos secundarios. Los administradores y técnicos son usuarios con roles. Las relaciones N:M condensan `role_user`, `ticket_equipment_links` y `ticket_supply_usages`; no representan claves foráneas directas.

```mermaid
erDiagram
    direction LR
    roles {
        integer id PK
        varchar name UK
    }
    users {
        integer id PK
        varchar name
        varchar email UK
    }
    ticket_categories {
        integer id PK
        varchar name
    }
    ticket_statuses {
        integer id PK
        varchar name UK
    }
    ticket_priorities {
        integer id PK
        varchar name UK
    }
    tickets {
        integer id PK
        varchar number UK
        varchar title
        integer requester_id FK
        integer current_assignee_id FK
    }
    ticket_comments {
        integer id PK
        integer ticket_id FK
        text body
    }
    ticket_approvals {
        integer id PK
        integer ticket_id FK
        varchar status
    }
    development_requests {
        integer id PK
        integer ticket_id FK, UK
        varchar project_name
    }
    equipment {
        integer id PK
        varchar inventory_code UK
        varchar name
    }
    supplies {
        integer id PK
        varchar name
        numeric stock_quantity
    }
    maintenance_orders {
        integer id PK
        integer equipment_id FK
        date scheduled_for
        varchar status
    }
    users ||..o{ tickets : "solicita"
    users |o..o{ tickets : "atiende"
    ticket_categories ||..o{ tickets : "clasifica"
    ticket_statuses |o..o{ tickets : "estado"
    ticket_priorities |o..o{ tickets : "prioridad"
    tickets ||..o{ ticket_comments : "comentarios"
    tickets ||..o{ ticket_approvals : "aprobaciones"
    tickets ||..o| development_requests : "desarrollo"
    tickets |o..o| maintenance_orders : "vincula"
    equipment ||..o{ maintenance_orders : "recibe"
    roles }o..o{ users : "role_user"
    tickets }o..o{ equipment : "ticket_equipment_links"
    tickets }o..o{ supplies : "ticket_supply_usages"
```

Actualizar: `php docs/erd/generar.php` y luego `node docs/erd/renderizar.cjs /ruta/a/@viz-js/viz`. Para renderizar el modelo completo se puede añadir `--complete`.
