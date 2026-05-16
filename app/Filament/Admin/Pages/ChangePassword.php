<?php

namespace App\Filament\Admin\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.change-password';

    protected static ?string $title = 'Đổi mật khẩu';

    /** Chỉ truy cập qua menu người dùng (avatar), không hiện trên sidebar. */
    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Đổi mật khẩu')
                    ->description('Nhập mật khẩu hiện tại và mật khẩu mới để cập nhật.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Mật khẩu hiện tại')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->currentPassword()
                            ->autocomplete('current-password')
                            ->dehydrated(false),
                        TextInput::make('password')
                            ->label('Mật khẩu mới')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->rule(Password::defaults())
                            ->autocomplete('new-password')
                            ->same('password_confirmation'),
                        TextInput::make('password_confirmation')
                            ->label('Xác nhận mật khẩu mới')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->autocomplete('new-password')
                            ->dehydrated(false),
                        Actions::make([
                            FormAction::make('save')
                                ->label('Lưu mật khẩu mới')
                                ->icon('heroicon-m-check')
                                ->action(fn () => $this->save()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
            ]);
        }

        $this->form->fill([
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        Notification::make()
            ->title('Đã đổi mật khẩu thành công')
            ->success()
            ->send();
    }
}
