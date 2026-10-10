export type Action =
    | 'contact'
    | 'request_information'
    | 'resolve_objection'
    | 'escalate'
    | 'wait'
    | 'no_action';
export type Decision = {
    case_id: string;
    action: Action;
    timing: string;
    scheduled_for: string | null;
    reason: string;
    evidence_event_ids: string[];
    confidence: string;
    confidence_reason: string;
    risk_level: string;
    requires_human_review: boolean;
    missing_information: string[];
};
export type CaseRow = {
    id: number;
    external_id: string;
    title: string;
    status: string;
    stage: string;
    amount_cents: number | null;
    revision: number;
    mode: 'kit' | 'live';
    set_name: string;
    customer: string | null;
    integrity_blocked: boolean;
    recommendation: Decision | null;
    source: string | null;
    publication_status: string | null;
    evaluation_status: string | null;
    error_message: string | null;
    updated_at: string;
};
export type Budget = {
    daily_micromxn: number;
    weekly_micromxn: number;
    daily_calls: number;
    daily_limit_mxn: number;
    weekly_limit_mxn: number;
    uncertain_calls: number;
};
export type Customer = {
    id: number;
    company: string;
    contact_name: string | null;
    email: string | null;
    notes: string | null;
};
export type Product = {
    id: number;
    sku: string;
    name: string;
    category: string;
    fit: string | null;
    gsm: number | null;
    composition: string | null;
    color: string | null;
    size: string | null;
    unit_price_cents: number | null;
    moq: number | null;
    stock: number | null;
    verified_at: string | null;
    source: string;
};
export type QuoteLine = {
    line_id: string;
    product_id: number;
    sku: string;
    name: string;
    fit: string | null;
    gsm: number | null;
    composition: string | null;
    color: string | null;
    size: string | null;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
    supply_mode: string;
    stock_units: number | null;
    manufacture_units: number | null;
    observed_stock: number | null;
    moq: number | null;
    below_moq: boolean;
};
export type Quote = {
    id: number;
    version: number;
    lines: QuoteLine[];
    subtotal_cents: number;
    tax_cents: number;
    shipping_cents: number;
    discount_cents: number;
    total_cents: number;
    terms: string | null;
    valid_until: string;
    authorization_reference: string;
    expired?: boolean;
};
export type CaseEvent = {
    event_id: string;
    event_type: string;
    occurred_at: string;
    sequence: number;
    data: Record<string, unknown>;
};
export type Signal = { kind: string; event_id: string; quote: string };
export type Recommendation = {
    id: number;
    result: Decision;
    signals: Signal[];
    source: string;
    publication_status: string;
    valid_until: string;
    created_at: string;
    is_current: boolean;
    review: { decision: string; reason: string } | null;
    feedback: { verdict: string; reason: string }[];
    alternatives: {
        action: Action;
        allowed: boolean;
        selected: boolean;
        excluded_by: string[];
    }[];
};
export type Evaluation = {
    id: number;
    status: string;
    provider: string;
    model: string | null;
    error_code?: string;
    error_message: string | null;
    duration_ms: number | null;
    created_at: string;
};
export type DataSet = {
    id: number;
    name: string;
    mode: 'kit' | 'live';
    evaluation_at: string | null;
    integrity_blocked: boolean;
};
