<?php

namespace App\Filament\Pages;

use App\Domain\Shared\Actor;
use App\Filament\Concerns\InNavigationGroup;
use App\Mail\AdminSaleAlertMail;
use App\Mail\BookingInvoiceMail;
use App\Mail\LoginAlertMail;
use App\Mail\PaymentFailedMail;
use App\Models\Order;
use App\Support\Formats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Email preview: renders the real mailables against a real booking in a
 * sandboxed frame (no scripts), desktop / phone widths, AR / EN, with an
 * optional test send to the signed-in admin only.
 */
class EmailPreview extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.email-preview';

    protected static string $navGroup = 'system';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'email-preview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    public const TEMPLATES = ['invoice', 'payment_failed', 'sale_alert', 'login_alert'];

    #[Url]
    public string $template = 'invoice';

    #[Url(as: 'order')]
    public ?string $orderId = null;

    #[Url]
    public string $lang = 'ar';

    public string $width = 'desktop';

    public static function getNavigationLabel(): string
    {
        return __('admin.mail.title');
    }

    public function getTitle(): string
    {
        return __('admin.mail.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.mail.sub');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('settings.view');
    }

    private function order(): ?Order
    {
        $q = Order::query()->with(['customer', 'event', 'holders', 'posUser']);

        return ($this->orderId ? (clone $q)->find($this->orderId) : null) ?? $q->latest()->first();
    }

    private function mailable(?Order $order): ?Mailable
    {
        $template = in_array($this->template, self::TEMPLATES, true) ? $this->template : 'invoice';
        $mail = match ($template) {
            'login_alert' => new LoginAlertMail('staff@example.com', 'account', (int) config('qcamelmc.auth.login_max', 5), '203.0.113.24', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)'),
            default => $order ? match ($template) {
                'payment_failed' => new PaymentFailedMail($order, __('admin.mail.sample_reason', [], $this->lang)),
                'sale_alert' => new AdminSaleAlertMail($order),
                default => new BookingInvoiceMail($order),
            } : null,
        };

        return $mail?->locale(in_array($this->lang, ['ar', 'en'], true) ? $this->lang : 'ar');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')->label(__('admin.mail.send_test'))->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn () => __('admin.mail.send_test_desc', ['email' => auth()->user()?->email]))
                ->action(function () {
                    $mail = $this->mailable($this->order());
                    $to = auth()->user()?->email;
                    if (! $mail || ! $to) {
                        Notification::make()->warning()->title(__('admin.mail.no_order'))->send();

                        return;
                    }
                    try {
                        Mail::to($to)->send($mail);
                        Notification::make()->success()->title(__('admin.mail.sent', ['email' => $to]))->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()->title(__('admin.mail.send_failed'))->body($e->getMessage())->send();
                    }
                }),
        ];
    }

    protected function getViewData(): array
    {
        $order = $this->order();
        $mail = $this->mailable($order);
        $html = $subject = null;
        $error = null;
        if ($mail) {
            try {
                $html = $mail->render();
                $subject = $mail->withLocale($mail->locale ?? 'ar', fn () => $mail->envelope()->subject);
            } catch (Throwable $e) {
                report($e);
                $error = $e->getMessage();
            }
        }

        return [
            'html' => $html,
            'subject' => $subject,
            'error' => $error,
            'order' => $order,
            'orders' => Order::query()->with('customer:id,name')->latest()->limit(30)->get(['id', 'booking_reference', 'customer_id', 'payment_status', 'created_at'])
                ->mapWithKeys(fn (Order $o) => [$o->id => $o->booking_reference.' — '.($o->customer?->name ?? '—').' · '.$o->payment_status?->label()])->all(),
            'when' => $order ? Formats::longDateTime($order->created_at) : null,
        ];
    }
}
