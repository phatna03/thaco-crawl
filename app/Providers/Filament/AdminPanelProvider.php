<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\AdminDashboard;
use App\Filament\Admin\Pages\ChangePassword;
use App\Filament\Admin\Pages\ImportContent;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('CMS Crawler')
            ->maxContentWidth(MaxWidth::Full)
            ->renderHook(
                'panels::styles.after',
                function (): string {
                    if (! request()->routeIs('filament.admin.resources.posts.index')) {
                        return '';
                    }

                    return view('filament.admin.hooks.posts-table-mobile-styles')->render();
                },
            )
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->pages([
                AdminDashboard::class,
                ImportContent::class,
                ChangePassword::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Đổi mật khẩu')
                    ->icon('heroicon-o-key')
                    ->url(fn (): string => ChangePassword::getUrl()),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
