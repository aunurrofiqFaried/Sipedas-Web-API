<?php

namespace App\Providers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $alasan = fn (Model $record): ?string => method_exists($record, 'alasanTidakBisaDihapus')
            ? $record->alasanTidakBisaDihapus()
            : null;

        // Hapus satu data: tolak dengan pesan yang jelas bila masih punya riwayat
        DeleteAction::configureUsing(
            fn (DeleteAction $action) => $action->before(function (DeleteAction $action, Model $record) use ($alasan) {
                if ($pesan = $alasan($record)) {
                    Notification::make()
                        ->title('Tidak bisa dihapus')
                        ->body($pesan)
                        ->danger()
                        ->persistent()
                        ->send();

                    $action->cancel();
                }
            })
        );

        // Hapus banyak data: bila ada satu saja yang tertahan, batalkan semuanya supaya tidak setengah jalan
        DeleteBulkAction::configureUsing(
            fn (DeleteBulkAction $action) => $action->before(function (DeleteBulkAction $action, Enumerable $records) use ($alasan) {
                $tertahan = $records->filter(fn (Model $r) => $alasan($r) !== null);

                if ($tertahan->isNotEmpty()) {
                    Notification::make()
                        ->title($tertahan->count() . ' dari ' . $records->count() . ' data tidak bisa dihapus')
                        ->body($alasan($tertahan->first()))
                        ->danger()
                        ->persistent()
                        ->send();

                    $action->cancel();
                }
            })
        );
    }
}