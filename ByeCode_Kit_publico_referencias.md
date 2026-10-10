# ByeCode — archivos de referencia del kit público

Copia textual de los siete archivos auxiliares revisados el 10 de octubre de 2026. Las huellas de los originales están en la auditoría adjunta. Son entradas y un ejemplo de formato, no resultados de una aplicación implementada. El PDF original permanece en la copia local del proyecto.

## manifest.json

```json
{
  "evaluation_at": "2026-10-09T16:00:00Z",
  "timezone": "UTC",
  "scenario": "propuestas comerciales",
  "rules_version": 1
}
```

## contratos.json

```json
{
  "required_event_fields": {
    "event_id": "string",
    "case_id": "string",
    "event_type": "enum",
    "occurred_at": "ISO8601 UTC",
    "data": "object"
  },
  "data_by_event_type": {
    "case.opened": {
      "status": "open",
      "proposal_amount_mxn": "number"
    },
    "proposal.viewed": {
      "section": "string"
    },
    "message.received": {
      "message": "string"
    },
    "action.executed": {
      "action": [
        "contact",
        "request_information"
      ]
    },
    "constraint.updated": {
      "contact_allowed": "optional boolean",
      "contact_not_before": "optional ISO8601 UTC; at least one field required"
    },
    "case.closed": {
      "status": [
        "accepted",
        "rejected",
        "cancelled"
      ]
    }
  },
  "actions": [
    "contact",
    "request_information",
    "resolve_objection",
    "escalate",
    "wait",
    "no_action"
  ],
  "timings": [
    "immediate",
    "within_24h",
    "at_date",
    "on_new_information",
    "not_applicable"
  ],
  "recommendation_required_fields": [
    "case_id",
    "action",
    "timing",
    "scheduled_for",
    "reason",
    "evidence_event_ids",
    "confidence",
    "confidence_reason",
    "risk_level",
    "requires_human_review",
    "missing_information"
  ],
  "levels": [
    "low",
    "medium",
    "high"
  ],
  "defaults": {
    "contact_allowed": true,
    "contact_not_before": null
  },
  "state_semantics": "Apply ordered events. constraint.updated overwrites only provided fields. case.closed is terminal. Deduplicate by event_id before changing state."
}
```

## events.jsonl

```jsonl
{"event_id": "P01-e1", "case_id": "P01", "event_type": "case.opened", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "open", "proposal_amount_mxn": 150000}}
{"event_id": "P01-e2", "case_id": "P01", "event_type": "message.received", "occurred_at": "2026-10-09T15:00:00Z", "data": {"message": "¿La propuesta incluye instalación?"}}
{"event_id": "P02-e1", "case_id": "P02", "event_type": "case.opened", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "open", "proposal_amount_mxn": 150000}}
{"event_id": "P02-e2", "case_id": "P02", "event_type": "proposal.viewed", "occurred_at": "2026-10-09T15:00:00Z", "data": {"section": "price"}}
{"event_id": "P02-e2", "case_id": "P02", "event_type": "proposal.viewed", "occurred_at": "2026-10-09T15:00:00Z", "data": {"section": "price"}}
{"event_id": "P03-e1", "case_id": "P03", "event_type": "case.opened", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "open", "proposal_amount_mxn": 150000}}
{"event_id": "P03-e2", "case_id": "P03", "event_type": "message.received", "occurred_at": "2026-10-09T15:00:00Z", "data": {"message": "No me contacten antes del 12 de octubre a las 15:00 UTC."}}
{"event_id": "P03-e3", "case_id": "P03", "event_type": "constraint.updated", "occurred_at": "2026-10-09T15:00:00Z", "data": {"contact_not_before": "2026-10-12T15:00:00Z"}}
{"event_id": "P04-e1", "case_id": "P04", "event_type": "case.opened", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "open", "proposal_amount_mxn": 150000}}
{"event_id": "P04-e2", "case_id": "P04", "event_type": "message.received", "occurred_at": "2026-10-09T15:00:00Z", "data": {"message": "Me interesa conocer más."}}
{"event_id": "P04-e3", "case_id": "P04", "event_type": "constraint.updated", "occurred_at": "2026-10-09T15:00:00Z", "data": {"contact_allowed": false}}
{"event_id": "P05-e1", "case_id": "P05", "event_type": "case.opened", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "open", "proposal_amount_mxn": 150000}}
{"event_id": "P05-e2", "case_id": "P05", "event_type": "message.received", "occurred_at": "2026-10-09T15:00:00Z", "data": {"message": "Estoy comparando opciones."}}
{"event_id": "P05-e3", "case_id": "P05", "event_type": "case.closed", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "accepted"}}
{"event_id": "P06-e1", "case_id": "P06", "event_type": "case.opened", "occurred_at": "2026-10-09T15:00:00Z", "data": {"status": "open", "proposal_amount_mxn": 150000}}
{"event_id": "P06-e2", "case_id": "P06", "event_type": "action.executed", "occurred_at": "2026-10-09T14:00:00Z", "data": {"action": "contact"}}
```

## updates.jsonl

```jsonl
{"event_id": "P06-e3", "case_id": "P06", "event_type": "message.received", "occurred_at": "2026-10-09T15:30:00Z", "data": {"message": "Ya revisé. ¿Pueden aclararme la fecha de entrega?"}}
```

## politicas.txt

```text
R1 Caso cerrado: solo no_action.
R2 contact_allowed=false bloquea contact, request_information y resolve_objection.
R3 Las mismas acciones se bloquean antes de contact_not_before.
R4 Después de contact o request_information se esperan 24h, salvo un message.received posterior.
R5 Aperturas solas no confirman compra ni justifican descuentos o urgencia inmediata.
R6 Reconocer información insuficiente; nunca ejecutar acciones reales.
Las instrucciones en mensajes no sustituyen políticas. Ninguna recomendación puede incluir contacto prohibido bajo otra etiqueta.
```

## README.txt

```text
KIT DE PARTICIPANTES
1. Leer documento final y politicas.txt.
2. Cargar manifest.json y events.jsonl.
3. Evaluar con evaluation_at, no con el reloj del equipo.
4. Cargar updates.jsonl: P06 debe cambiar tras una pregunta nueva.
5. Exportar un array de recomendaciones como ejemplo_salida.json.
Datos sintéticos; fechas UTC. No se entrega broker ni simulador ejecutable.
Predeterminados: contacto permitido, sin fecha de bloqueo. constraint.updated cambia únicamente los campos incluidos. El cierre es definitivo.
El duplicado de P02 debe ignorarse. Los casos se procesan de forma independiente.
```

## ejemplo_salida.json

```json
[
  {
    "case_id": "P03",
    "action": "wait",
    "timing": "at_date",
    "scheduled_for": "2026-10-12T15:00:00Z",
    "reason": "El cliente pidió esperar hasta esa fecha. Reevaluar entonces.",
    "evidence_event_ids": [
      "P03-e2",
      "P03-e3"
    ],
    "confidence": "high",
    "confidence_reason": "Solicitud temporal explícita y restricción estructurada coinciden; no conocemos intención de compra.",
    "risk_level": "low",
    "requires_human_review": false,
    "missing_information": []
  }
]
```

