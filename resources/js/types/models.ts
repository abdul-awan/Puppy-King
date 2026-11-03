/**
 * Shared types for the data the server sends to the pages.
 */

export type ModuleKey =
    | 'crm'
    | 'deals'
    | 'quotations'
    | 'invoicing'
    | 'expenses'
    | 'daily_expenses'
    | 'payroll'
    | 'products'
    | 'projects'
    | 'time_tracking'
    | 'planning'
    | 'tickets'
    | 'calendar'
    | 'work_orders'
    | 'reports'
    | 'settings';

export type AccessLevel = 'none' | 'read' | 'write';

export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type IdName = {
    id: number;
    name: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type LookupResult = {
    id: number;
    label: string;
    description: string | null;
    data?: Record<string, unknown>;
};

export type DealStatus = 'open' | 'won' | 'lost';

export type PhaseOption = {
    id: number;
    name: string;
    probability: number;
};

export type PipelineOption = {
    id: number;
    name: string;
    phases: PhaseOption[];
};

export type Deal = {
    id: number;
    title: string;
    value: string;
    currency: string;
    probability: number | null;
    effective_probability?: number;
    weighted_value?: number;
    expected_close_date: string | null;
    status: DealStatus;
    status_label: string;
    status_color: string;
    closed_at: string | null;
    description: string | null;
    lost_note: string | null;
    custom_fields: CustomFieldValues;
    sort_order: number;
    created_at: string;
    pipeline_id: number;
    phase_id: number;
    company_id: number | null;
    contact_id: number | null;
    responsible_user_id: number | null;
    source_id: number | null;
    department_id: number | null;
    lost_reason_id: number | null;
    pipeline?: IdName;
    phase?: PhaseOption;
    company?: IdName | null;
    contact?: IdName | null;
    responsible_user?: IdName | null;
    source?: IdName | null;
    lost_reason?: IdName | null;
    tags?: Tag[];
};

export type BoardPhase = PhaseOption & {
    count: number;
    total: number;
    weighted: number;
    deals: Deal[];
};

export type LineType = 'item' | 'section' | 'text';

export type DocumentLine = {
    id?: number;
    type: LineType;
    product_id: number | null;
    description: string;
    details: string | null;
    quantity: string;
    unit: string | null;
    unit_price: string;
    discount_percent: string;
    tax_rate_id: number | null;
    tax_rate: string;
    tax_name: string | null;
    hs_code: string | null;
    subtotal: string;
    net_amount: string;
    tax_amount: string;
    total: string;
};

export type TaxBreakdown = {
    name: string;
    rate: string;
    net: string;
    tax: string;
};

export type DocumentCustomer = {
    name: string | null;
    address: string | null;
    email: string | null;
    ntn: string | null;
    strn: string | null;
    cnic: string | null;
    registered: boolean;
};

export type EmailDraft = {
    to: string;
    cc: string;
    subject: string;
    body: string;
};

export type Quotation = {
    id: number;
    number: string | null;
    title: string | null;
    date: string;
    valid_until: string | null;
    status: 'draft' | 'sent' | 'accepted' | 'declined';
    status_label: string;
    status_color: string;
    is_expired: boolean;
    currency: string;
    exchange_rate: string;
    discount_percent: string;
    subtotal: string;
    discount_amount: string;
    tax_total: string;
    further_tax_rate: string | null;
    further_tax_amount: string;
    total: string;
    customer: DocumentCustomer;
    company_id: number | null;
    contact_id: number | null;
    deal_id: number | null;
    department_id: number | null;
    company?: IdName | null;
    contact?: IdName | null;
    deal?: { id: number; title: string } | null;
    department?: IdName | null;
    introduction: string | null;
    terms: string | null;
    notes: string | null;
    public_url: string;
    sent_at: string | null;
    accepted_at: string | null;
    declined_at: string | null;
    accepted_by_name: string | null;
    decline_reason: string | null;
    invoiced_at: string | null;
    has_signature: boolean;
    lines?: DocumentLine[];
    taxes?: TaxBreakdown[];
    created_at: string;
    creator?: IdName | null;
};

export type DocumentSummary = {
    id: number;
    type: string;
    number: string | null;
    title: string | null;
    date: string;
    status_label: string;
    status_color: string;
    total: string;
    currency: string;
    customer: string | null;
    url: string;
};

export type RelatedDocumentGroup = {
    items: DocumentSummary[];
    total: number;
} | null;

export type RelatedDocumentKey =
    | 'tickets'
    | 'work_orders'
    | 'projects'
    | 'quotations'
    | 'invoices'
    | 'credit_notes'
    | 'expenses';

export type RelatedDocumentGroups = Partial<
    Record<RelatedDocumentKey, RelatedDocumentGroup>
>;

/**
 * Fields shared by invoices and credit notes.
 */
export type SalesDocumentBase = {
    id: number;
    number: string | null;
    title: string | null;
    date: string;
    status_label: string;
    status_color: string;
    currency: string;
    exchange_rate: string;
    discount_percent: string;
    subtotal: string;
    discount_amount: string;
    tax_total: string;
    further_tax_rate: string | null;
    further_tax_amount: string;
    total: string;
    customer: DocumentCustomer;
    company_id: number | null;
    contact_id: number | null;
    department_id: number | null;
    company?: IdName | null;
    contact?: IdName | null;
    department?: IdName | null;
    introduction: string | null;
    terms: string | null;
    notes: string | null;
    public_url: string;
    lines?: DocumentLine[];
    taxes?: TaxBreakdown[];
    created_at: string;
    creator?: IdName | null;
};

export type PaymentMethodValue =
    | 'bank_transfer'
    | 'cheque'
    | 'cash'
    | 'online'
    | 'card'
    | 'other';

export type InvoicePayment = {
    id: number;
    invoice_id: number;
    date: string;
    amount: string;
    income_tax_withheld: string;
    sales_tax_withheld: string;
    settled_amount: string;
    method: PaymentMethodValue;
    method_label: string;
    reference: string | null;
    notes: string | null;
    user?: IdName | null;
    invoice?: {
        id: number;
        number: string | null;
        customer: string | null;
        currency: string;
    };
    created_at: string;
};

export type Invoice = SalesDocumentBase & {
    status: 'draft' | 'open' | 'paid' | 'credited';
    due_date: string | null;
    is_overdue: boolean;
    days_overdue: number;
    amount_paid: string;
    amount_credited: string;
    balance_due: string;
    creditable_amount: string;
    fbr_invoice_number: string | null;
    deal_id: number | null;
    quotation_id: number | null;
    payment_term_id: number | null;
    recurring_invoice_id: number | null;
    deal?: { id: number; title: string } | null;
    quotation?: { id: number; number: string | null } | null;
    payment_term?: IdName | null;
    recurring_invoice?: { id: number; title: string } | null;
    payments?: InvoicePayment[];
    credit_notes?: DocumentSummary[];
    issued_at: string | null;
    sent_at: string | null;
    paid_at: string | null;
    reminder_count: number;
    last_reminder_at: string | null;
};

export type CreditNote = SalesDocumentBase & {
    status: 'draft' | 'issued';
    reason: string | null;
    restock: boolean;
    invoice_id: number;
    invoice?: {
        id: number;
        number: string | null;
        date: string;
        total: string;
        creditable_amount: string;
    };
    issued_at: string | null;
    sent_at: string | null;
};

export type RecurringFrequencyValue =
    | 'weekly'
    | 'monthly'
    | 'quarterly'
    | 'half_yearly'
    | 'yearly';

export type RecurringInvoice = {
    id: number;
    title: string;
    frequency: RecurringFrequencyValue;
    frequency_label: string;
    start_date: string;
    end_date: string | null;
    next_date: string | null;
    status_label: string;
    status_color: string;
    generated_count: number;
    auto_issue: boolean;
    auto_send: boolean;
    is_active: boolean;
    currency: string;
    exchange_rate: string;
    discount_percent: string;
    subtotal: string;
    discount_amount: string;
    tax_total: string;
    further_tax_rate: string | null;
    further_tax_amount: string;
    total: string;
    customer_name: string | null;
    company_id: number | null;
    contact_id: number | null;
    department_id: number | null;
    payment_term_id: number | null;
    company?: IdName | null;
    contact?: IdName | null;
    payment_term?: IdName | null;
    introduction: string | null;
    terms: string | null;
    notes: string | null;
    lines?: DocumentLine[];
    taxes?: TaxBreakdown[];
    last_generated_at: string | null;
    created_at: string;
};

export type ExpensePayment = {
    id: number;
    date: string;
    amount: string;
    method: PaymentMethodValue;
    method_label: string;
    reference: string | null;
    notes: string | null;
    user: IdName | null;
};

export type Expense = {
    id: number;
    number: string | null;
    title: string;
    company_id: number | null;
    supplier?: IdName | null;
    supplier_name: string | null;
    supplier_ntn: string | null;
    supplier_strn: string | null;
    bill_number: string | null;
    category_id: number | null;
    category?: IdName | null;
    date: string;
    due_date: string | null;
    currency: string;
    exchange_rate: string;
    net_amount: string;
    tax_rate_id: number | null;
    tax_rate: string;
    tax_name?: string | null;
    tax_amount: string;
    total: string;
    tax_claimable: boolean;
    income_tax_withheld: string;
    sales_tax_withheld: string;
    payable: string;
    amount_paid: string;
    balance_due: string;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    status_color: string;
    is_overdue: boolean;
    is_paid: boolean;
    approver?: IdName | null;
    approved_at: string | null;
    rejection_reason: string | null;
    paid_at: string | null;
    notes: string | null;
    payments?: ExpensePayment[];
    creator?: IdName | null;
    created_at: string;
};

export type ProjectStatusValue =
    | 'planned'
    | 'active'
    | 'on_hold'
    | 'done'
    | 'cancelled';

export type BillingMethodValue =
    | 'time_and_materials'
    | 'fixed_price'
    | 'non_billable';

export type Project = {
    id: number;
    number: string | null;
    title: string;
    description: string | null;
    status: ProjectStatusValue;
    status_label: string;
    status_color: string;
    is_open: boolean;
    is_overdue: boolean;
    billing_method: BillingMethodValue;
    billing_method_label: string;
    start_date: string | null;
    due_date: string | null;
    budget_amount: string | null;
    budget_hours: string | null;
    hourly_rate: string | null;
    currency: string;
    company_id: number | null;
    contact_id: number | null;
    deal_id: number | null;
    quotation_id: number | null;
    responsible_user_id: number | null;
    customer_name: string | null;
    company?: IdName | null;
    contact?: IdName | null;
    deal?: { id: number; title: string } | null;
    quotation?: { id: number; number: string | null } | null;
    responsible_user?: IdName | null;
    members?: IdName[];
    tasks_count?: number;
    done_tasks_count?: number;
    logged_minutes?: number;
    completed_at: string | null;
    created_at: string;
};

export type ProjectMetrics = {
    logged_minutes: number;
    billable_minutes: number;
    estimated_minutes: number;
    budget_minutes: number | null;
    time_value: number;
    materials_value: number;
    work_value: number;
    unbilled_minutes: number;
    unbilled_value: number;
    materials_cost: number;
    expenses: number;
    invoiced: number;
    budget_used_percent: number | null;
    tasks_total: number;
    tasks_done: number;
    progress_percent: number | null;
};

export type TaskStatusValue = 'todo' | 'in_progress' | 'done';

export type TaskPriorityValue = 'low' | 'normal' | 'high' | 'urgent';

export type Task = {
    id: number;
    title: string;
    description: string | null;
    status: TaskStatusValue;
    status_label: string;
    status_color: string;
    priority: TaskPriorityValue;
    priority_label: string;
    priority_color: string;
    due_date: string | null;
    is_overdue: boolean;
    estimated_minutes: number | null;
    is_billable: boolean;
    project_id: number | null;
    milestone_id: number | null;
    company_id: number | null;
    deal_id: number | null;
    assignee_id: number | null;
    work_type_id: number | null;
    project?: { id: number; number: string | null; title: string } | null;
    company?: IdName | null;
    deal?: { id: number; title: string } | null;
    assignee?: IdName | null;
    logged_minutes?: number;
    completed_at: string | null;
    created_at: string;
};

export type Milestone = {
    id: number;
    name: string;
    due_date: string | null;
};

export type TimeEntry = {
    id: number;
    user_id: number | null;
    date: string;
    started_at: string | null;
    ended_at: string | null;
    is_running: boolean;
    duration_minutes: number;
    description: string | null;
    is_billable: boolean;
    hourly_rate: string;
    value: string;
    project_id: number | null;
    task_id: number | null;
    ticket_id: number | null;
    work_order_id: number | null;
    company_id: number | null;
    work_type_id: number | null;
    invoice_id: number | null;
    user?: IdName | null;
    project?: { id: number; number: string | null; title: string } | null;
    task?: { id: number; title: string } | null;
    company?: IdName | null;
    work_type?: IdName | null;
    invoice?: { id: number; number: string | null } | null;
};

export type ProjectMaterial = {
    id: number;
    project_id: number | null;
    work_order_id: number | null;
    product_id: number | null;
    date: string;
    description: string;
    quantity: string;
    unit: string | null;
    unit_price: string;
    unit_cost: string | null;
    tax_rate_id: number | null;
    is_billable: boolean;
    value: string;
    invoice_id: number | null;
    invoice?: { id: number; number: string | null } | null;
};

export type WorkTypeOption = {
    id: number;
    name: string;
    hourly_rate: string | null;
    is_billable: boolean;
};

/** A company (workspace) as platform administrators see it. */
export type AdminCompany = {
    slug: string;
    name: string;
    owner: { id: number; name: string; email: string } | null;
    members_count: number;
    is_personal: boolean;
    suspended_at: string | null;
    created_at: string | null;
};

/** A user as platform administrators see them. */
export type AdminUser = {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    is_platform_admin: boolean;
    teams_count: number;
    created_at: string | null;
};

export type AppNotification = {
    id: string;
    kind: string;
    title: string;
    body: string | null;
    read: boolean;
    created_at: string | null;
};

export type NotificationFeed = {
    unread: number;
    items: AppNotification[];
};

export type RunningTimer = {
    id: number;
    started_at: string | null;
    description: string | null;
    project: string | null;
    minutes_before: number;
};

export type CalendarEventItem = {
    id: number;
    type: string;
    type_label: string;
    color: string;
    title: string;
    description: string | null;
    location: string | null;
    starts_at: string;
    ends_at: string;
    all_day: boolean;
    status: 'planned' | 'done' | 'cancelled';
    status_label: string;
    outcome: string | null;
    reminder_minutes: number | null;
    organizer_id: number | null;
    company_id: number | null;
    contact_id: number | null;
    deal_id: number | null;
    project_id: number | null;
    organizer?: IdName | null;
    attendees?: IdName[];
    company?: IdName | null;
    contact?: IdName | null;
    deal?: { id: number; title: string } | null;
    project?: { id: number; title: string } | null;
};

export type CalendarTask = {
    id: number;
    title: string;
    due_date: string | null;
    project: string | null;
    priority: string;
};

export type TicketMessage = {
    id: number;
    body: string;
    is_internal: boolean;
    from_customer: boolean;
    author: string;
    emailed_at: string | null;
    created_at: string;
};

export type Ticket = {
    id: number;
    number: string | null;
    subject: string;
    status: 'new' | 'open' | 'waiting' | 'closed';
    status_label: string;
    status_color: string;
    priority: TaskPriorityValue;
    priority_label: string;
    priority_color: string;
    channel: string;
    channel_label: string;
    company_id: number | null;
    contact_id: number | null;
    project_id: number | null;
    assignee_id: number | null;
    customer_name: string | null;
    customer_email: string | null;
    customer_label: string;
    reply_to: string | null;
    company?: IdName | null;
    contact?: IdName | null;
    project?: { id: number; title: string } | null;
    assignee?: IdName | null;
    messages?: TicketMessage[];
    messages_count?: number;
    public_url: string;
    first_response_at: string | null;
    last_customer_message_at: string | null;
    closed_at: string | null;
    created_at: string;
    updated_at: string;
};

export type WorkOrder = {
    id: number;
    number: string | null;
    title: string;
    date: string;
    status: 'open' | 'completed';
    display_status: 'open' | 'completed' | 'signed' | 'invoiced';
    status_label: string;
    status_color: string;
    company_id: number | null;
    contact_id: number | null;
    project_id: number | null;
    ticket_id: number | null;
    assignee_id: number | null;
    department_id: number | null;
    location: string | null;
    description: string | null;
    internal_notes: string | null;
    show_prices: boolean;
    customer_label: string;
    customer_email: string | null;
    completed_at: string | null;
    is_signed: boolean;
    signed_by_name: string | null;
    signed_at: string | null;
    customer_remarks: string | null;
    invoice_id: number | null;
    invoiced_at: string | null;
    public_url: string;
    logged_minutes?: number;
    company?: IdName | null;
    contact?: IdName | null;
    project?: { id: number; number: string | null; title: string } | null;
    ticket?: { id: number; number: string | null; subject: string } | null;
    assignee?: IdName | null;
    department?: IdName | null;
    invoice?: { id: number; number: string | null } | null;
    creator?: IdName | null;
    created_at: string;
    updated_at: string;
};

export type WorkOrderTotals = {
    minutes: number;
    billable_minutes: number;
    time_value: string;
    materials_value: string;
    total_value: string;
};

export type TaxRateOption = {
    id: number;
    name: string;
    rate: string;
    is_default: boolean;
};

export type Product = {
    id: number;
    type: 'product' | 'service';
    type_label: string;
    name: string;
    code: string | null;
    description: string | null;
    unit: string;
    sale_price: string;
    purchase_price: string | null;
    margin_percent: number | null;
    category_id: number | null;
    tax_rate_id: number | null;
    hs_code: string | null;
    track_stock: boolean;
    stock_quantity: string;
    is_active: boolean;
    custom_fields: CustomFieldValues;
    created_at: string;
    category?: IdName | null;
    tax_rate?: { id: number; name: string; rate: string } | null;
};

export type Tag = {
    id: number;
    name: string;
    color: string;
};

export type AddressType = 'primary' | 'billing' | 'shipping' | 'visiting';

export type Address = {
    id?: number;
    type: AddressType;
    type_label?: string;
    line_1: string | null;
    line_2: string | null;
    city: string | null;
    province: string | null;
    province_label?: string | null;
    postal_code: string | null;
    country: string | null;
    single_line?: string;
};

export type CustomFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'date'
    | 'select'
    | 'checkbox'
    | 'email'
    | 'url'
    | 'phone';

export type CustomFieldDefinition = {
    id: number;
    entity: string;
    entity_label: string;
    label: string;
    key: string;
    type: CustomFieldType;
    type_label: string;
    options: string[];
    is_required: boolean;
    sort: number;
};

export type CustomFieldValues = Record<
    string,
    string | number | boolean | null
>;

export type Attachment = {
    id: number;
    name: string;
    mime_type: string | null;
    size: number;
    user?: IdName | null;
    can_delete: boolean;
    created_at: string;
};

export type TimelineActivity = {
    key: string;
    kind: 'activity';
    event: string;
    description: string;
    user: IdName | null;
    created_at: string;
};

export type TimelineNote = {
    key: string;
    kind: 'note';
    id: number;
    body: string;
    is_pinned: boolean;
    can_edit: boolean;
    user: IdName | null;
    created_at: string;
};

export type TimelineEmail = {
    key: string;
    kind: 'email';
    id: number;
    to: string;
    subject: string;
    body: string;
    status: string;
    error: string | null;
    user: IdName | null;
    created_at: string;
};

export type TimelineEvent = {
    key: string;
    kind: 'event';
    id: number;
    type: string;
    type_label: string;
    title: string;
    location: string | null;
    starts_at: string;
    all_day: boolean;
    status: 'planned' | 'done' | 'cancelled';
    outcome: string | null;
    user: IdName | null;
    created_at: string;
};

export type TimelineItem =
    | TimelineActivity
    | TimelineNote
    | TimelineEmail
    | TimelineEvent;

export type PaymentTermOption = {
    id: number;
    name: string;
    days: number;
    end_of_month: boolean;
    is_default: boolean;
};

export type CompanyContactLink = {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    position: string | null;
    is_primary: boolean;
    is_decision_maker: boolean;
};

export type Company = {
    id: number;
    name: string;
    relationship: string;
    relationship_label: string;
    relationship_color: string;
    business_type: string | null;
    business_type_label: string | null;
    ntn: string | null;
    strn: string | null;
    cnic: string | null;
    sales_tax_registered: boolean;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    website: string | null;
    industry: string | null;
    responsible_user_id: number | null;
    payment_term_id: number | null;
    currency: string | null;
    language: string;
    iban: string | null;
    remarks: string | null;
    custom_fields: CustomFieldValues;
    archived_at: string | null;
    created_at: string;
    responsible_user?: IdName | null;
    payment_term?: IdName | null;
    tags?: Tag[];
    addresses?: Address[];
    primary_address?: string | null;
    contacts_count?: number;
    contacts?: CompanyContactLink[];
};

export type ContactCompanyLink = {
    id: number;
    name: string;
    position: string | null;
    is_primary: boolean;
    is_decision_maker: boolean;
};

export type Contact = {
    id: number;
    salutation: string | null;
    first_name: string | null;
    last_name: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    cnic: string | null;
    birthdate: string | null;
    language: string;
    responsible_user_id: number | null;
    marketing_consent: boolean;
    remarks: string | null;
    custom_fields: CustomFieldValues;
    archived_at: string | null;
    created_at: string;
    responsible_user?: IdName | null;
    tags?: Tag[];
    addresses?: Address[];
    companies?: ContactCompanyLink[];
};

export type PayComponentKind = 'earning' | 'deduction' | 'employer';

export type PayAmountType = 'fixed' | 'percent_basic' | 'percent_gross';

export type PayComponentOption = {
    id: number;
    name: string;
    kind: PayComponentKind;
    amount_type: PayAmountType;
    default_value: string;
    tax_label: string | null;
    is_active: boolean;
};

export type EmployeePayItem = {
    id: number;
    pay_component_id: number;
    name: string;
    kind: PayComponentKind;
    amount_type: PayAmountType;
    value: string;
    tax_label: string | null;
};

export type Employee = {
    id: number;
    code: string;
    name: string;
    father_name: string | null;
    cnic: string | null;
    date_of_birth: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    designation: string | null;
    department: string | null;
    employment_type: string;
    employment_type_label: string;
    joining_date: string;
    leaving_date: string | null;
    has_left: boolean;
    status_label: string;
    status_color: string;
    basic_salary: string;
    payment_method: PaymentMethodValue;
    payment_method_label: string;
    bank_name: string | null;
    bank_account_title: string | null;
    bank_account_number: string | null;
    tax_mode: 'auto' | 'fixed' | 'none';
    tax_mode_label: string;
    fixed_tax: string | null;
    eobi: boolean;
    eobi_number: string | null;
    opening_tax_year: number | null;
    opening_taxable: string | null;
    opening_tax: string | null;
    user_id: number | null;
    user?: { id: number; name: string; email: string } | null;
    notes: string | null;
    items?: EmployeePayItem[];
    creator?: IdName | null;
    created_at: string | null;
};

export type SalaryEstimate = {
    basic: string;
    gross: string;
    income_tax: string;
    deductions: string;
    net: string;
    employer: string;
    cost: string;
    yearly_taxable: string;
    yearly_tax: string;
    tax_year: number;
    table_year: number | null;
};

export type EmployeeAdvanceRow = {
    id: number;
    date: string;
    amount: string;
    installment: string;
    reason: string | null;
    recovered: string;
    scheduled: string;
    outstanding: string;
    can_delete: boolean;
};

export type PayrollRunStatusValue = 'draft' | 'approved' | 'paid';

export type PayrollRun = {
    id: number;
    period: string;
    label: string;
    tax_year: number;
    pay_date: string | null;
    status: PayrollRunStatusValue;
    status_label: string;
    status_color: string;
    employees_count: number;
    gross: string;
    deductions: string;
    income_tax: string;
    net: string;
    employer_cost: string;
    notes: string | null;
    approver?: IdName | null;
    approved_at: string | null;
    paid_at: string | null;
    creator?: IdName | null;
    created_at: string | null;
};

export type PayslipLine = {
    id: number;
    source: 'salary' | 'adjustment' | 'advance' | 'tax' | 'eobi';
    kind: PayComponentKind;
    name: string;
    amount: string;
    taxable_amount: string;
    pay_component_id: number | null;
    employee_advance_id: number | null;
};

export type PayslipTaxDetails = {
    tax_year: number;
    method: 'auto' | 'fixed' | 'none' | 'manual';
    table_year?: number | null;
    earlier_taxable?: string;
    earlier_tax?: string;
    opening_taxable?: string;
    opening_tax?: string;
    month_taxable?: string;
    one_off_taxable?: string;
    monthly_taxable?: string;
    months_left?: number;
    expected_income?: string;
    year_tax?: string;
};

export type Payslip = {
    id: number;
    payroll_run_id: number;
    employee_id: number;
    number: string | null;
    period: string;
    period_label: string;
    employee_name: string;
    employee_code: string;
    employee_email?: string | null;
    designation: string | null;
    department: string | null;
    cnic: string | null;
    payment_method: PaymentMethodValue;
    payment_method_label: string;
    bank_name: string | null;
    bank_account_title: string | null;
    bank_account_number: string | null;
    days_in_period: number;
    employed_days: number;
    unpaid_days: string;
    payable_days: string;
    basic_salary: string;
    gross: string;
    taxable_income: string;
    income_tax: string;
    tax_override: string | null;
    tax_details: PayslipTaxDetails | null;
    deductions: string;
    employer_contributions: string;
    net: string;
    paid_on: string | null;
    payment_reference: string | null;
    emailed_at: string | null;
    notes: string | null;
    lines?: PayslipLine[];
};

export type OpenAdvance = {
    id: number;
    label: string;
    left: string;
    installment: string;
};

export type DailyExpense = {
    id: number;
    number: string | null;
    date: string;
    category_id: number | null;
    category?: IdName | null;
    description: string;
    amount: string;
    payment_method: PaymentMethodValue;
    payment_method_label: string;
    paid_by: string | null;
    paid_to: string | null;
    reference: string | null;
    notes: string | null;
    attachments_count?: number;
    creator?: IdName | null;
    created_at: string | null;
};

export type CashMovement = {
    key: string;
    type: 'entry' | 'expense';
    id: number;
    date: string;
    number: string | null;
    description: string;
    person: string | null;
    in: string | null;
    out: string | null;
    balance: string;
};

export type PettyCashEntry = {
    id: number;
    date: string;
    direction: 'in' | 'out';
    amount: string;
    description: string;
    person: string | null;
    notes: string | null;
};

export type CashSummary = {
    opening: string;
    added: string;
    taken_out: string;
    expenses: string;
    closing: string;
};
