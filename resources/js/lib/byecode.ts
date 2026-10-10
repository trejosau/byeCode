import type { Action } from '@/types/byecode';
export const money = (cents: number | null) =>
    cents === null
        ? 'Pendiente de cotizar'
        : new Intl.NumberFormat('es-MX', {
              style: 'currency',
              currency: 'MXN',
              maximumFractionDigits: 2,
          }).format(cents / 100);
export const date = (value: string | null | undefined) =>
    value
        ? new Intl.DateTimeFormat('es-MX', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: 'America/Mexico_City',
          }).format(new Date(value))
        : 'Sin fecha';
export const actions: Record<Action, string> = {
    contact: 'Dar seguimiento',
    request_information: 'Solicitar información',
    resolve_objection: 'Resolver una duda',
    escalate: 'Revisión interna',
    wait: 'Esperar',
    no_action: 'Sin intervención',
};
export const timings: Record<string, string> = {
    immediate: 'Ahora',
    within_24h: 'En las próximas 24 h',
    at_date: 'En fecha indicada',
    on_new_information: 'Con nueva información',
    not_applicable: 'No aplica',
};
export const levels: Record<string, string> = {
    low: 'Bajo',
    medium: 'Medio',
    high: 'Alto',
};
export const statuses: Record<string, string> = {
    open: 'Abierta',
    accepted: 'Aceptada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
    pending: 'En cola',
    running: 'Evaluando',
    completed: 'Completada',
    failed: 'Falló la evaluación',
    blocked: 'Evaluación bloqueada',
    stale: 'Contexto anterior',
    awaiting_review: 'Pendiente de revisión',
    published: 'Validada',
    rules: 'Reglas',
    rules_contingency: 'Contingencia por reglas',
    openai: 'OpenAI',
    openai_cache: 'IA reutilizada y revalidada',
};
export const stages: Record<string, string> = {
    request_received: 'Solicitud recibida',
    proposal_sent: 'Propuesta enviada',
    proposal_viewed: 'Propuesta consultada',
    conversation: 'Conversación activa',
    closed: 'Caso cerrado',
};
export const eventNames: Record<string, string> = {
    'case.opened': 'Apertura del caso',
    'proposal.viewed': 'Consulta de la propuesta',
    'message.received': 'Mensaje recibido',
    'action.executed': 'Acción realizada',
    'constraint.updated': 'Restricción actualizada',
    'case.closed': 'Cierre confirmado',
    'quote.published': 'Nueva versión de cotización',
    'observation.recorded': 'Validación operativa',
};
export const signalNames: Record<string, string> = {
    question: 'Pregunta',
    objection: 'Objeción',
    interest: 'Interés expresado',
    change_request: 'Cambio solicitado',
    conditional_acceptance: 'Aceptación condicionada',
    partial_acceptance: 'Aceptación parcial',
    approval_pending: 'Aprobación pendiente',
    wait_request: 'Solicitud de espera',
    contact_refusal: 'Contacto rechazado',
    rejection: 'Rechazo expresado',
    ambiguity: 'Ambigüedad',
};
