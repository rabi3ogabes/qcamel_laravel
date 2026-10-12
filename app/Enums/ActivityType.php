<?php

namespace App\Enums;

/** Widened audit categories (3.12, D-025) → spatie activity_log.log_name. */
enum ActivityType: string
{
    use Concerns;

    case PosForm = 'pos_form';
    case InvoiceDownload = 'invoice_download';
    case QrSearch = 'qr_search';
    case TicketScan = 'ticket_scan';
    case TicketCheckinReset = 'ticket_checkin_reset';
    case EventChange = 'event_change';
    case NewEventStarted = 'new_event_started';
    case LoginBlocked = 'login_blocked';
    case OrderCancelled = 'order_cancelled';
    case OrderConfirmed = 'order_confirmed';
    case OrderDeleted = 'order_deleted';
    case StockRecount = 'stock_recount';
    case SettingsChanged = 'settings_changed';
    case StaffRoleChanged = 'staff_role_changed';
    case TicketBinned = 'ticket_binned';
    case TicketRestored = 'ticket_restored';
    case PriceChanged = 'price_changed';
    case CapacityChanged = 'capacity_changed';
    case EventUpdated = 'event_updated';
    case CustomerChanged = 'customer_changed';
    case InvoiceDispatch = 'invoice_dispatch';
    case DataManagement = 'data_management';
    case Auth = 'auth';
    case PaymentReview = 'payment_review';

    public static function langKey(): string
    {
        return 'activity_type';
    }

    /** Tabs of the activity log page (F-ADM-LOG). */
    public static function tabs(): array
    {
        return ['invoice_download', 'pos_form', 'qr_search', 'ticket_scan'];
    }
}
