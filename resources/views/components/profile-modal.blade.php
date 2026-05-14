<div x-data="{
    open: false,
    currentPassword: '',
    newPassword: '',
    newPasswordConfirmation: '',
    showCurrent: false,
    showNew: false,
    showConfirm: false,
    close() {
        this.open = false;
        this.currentPassword = '';
        this.newPassword = '';
        this.newPasswordConfirmation = '';
    }
}"
@open-profile-modal.window="open = true">

    <div class="overlay" x-show="open" x-cloak @click.self="close" style="z-index: 9999;">
        <div class="modal-center" x-show="open" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" style="max-width: 500px;">
            <div class="modal-hdr" style="background: var(--bg-2);">
                <div class="modal-ttl" style="font-family: var(--font-display);">ตั้งค่าบัญชี (Account Settings)</div>
                <button type="button" class="modal-cls" @click="close">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="modal-body" style="padding: 24px;">
                <!-- User Info (Read-only) -->
                <div style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--r-md); padding: 16px; margin-bottom: 24px;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">ข้อมูลผู้ใช้งาน</div>
                    <div style="display: grid; grid-template-columns: 100px 1fr; gap: 8px 16px; font-size: 14px;">
                        <div style="color: var(--fg-2);">ชื่อผู้ใช้:</div>
                        <div style="font-weight: 600; color: var(--fg-1);">{{ auth()->user()->username }}</div>
                        
                        <div style="color: var(--fg-2);">ชื่อ-นามสกุล:</div>
                        <div style="font-weight: 600; color: var(--fg-1);">{{ auth()->user()->formatted_name }}</div>
                        
                        @if(auth()->user()->email)
                        <div style="color: var(--fg-2);">อีเมล:</div>
                        <div style="font-weight: 600; color: var(--fg-1);">{{ auth()->user()->email }}</div>
                        @endif
                    </div>
                </div>

                <!-- Password Change Form -->
                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    
                    <div style="font-size: 15px; font-weight: 700; color: var(--fg-1); margin-bottom: 16px;">เปลี่ยนรหัสผ่าน</div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label>รหัสผ่านปัจจุบัน <span style="color: var(--status-conflict-fg);">*</span></label>
                        <div style="position: relative;">
                            <input :type="showCurrent ? 'text' : 'password'" name="current_password" x-model="currentPassword" required class="input" style="width: 100%; padding-right: 40px;">
                            <button type="button" @click="showCurrent = !showCurrent" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--fg-3);">
                                <svg x-show="!showCurrent" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showCurrent" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" x-cloak><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a2 2 0 1 1-2.83-2.83M1 1l22 22"/></svg>
                            </button>
                        </div>
                        @error('current_password')
                            <div style="color: var(--status-conflict-fg); font-size: 12px; margin-top: 4px; font-weight: 600;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label>รหัสผ่านใหม่ <span style="color: var(--status-conflict-fg);">*</span></label>
                        <div style="position: relative;">
                            <input :type="showNew ? 'text' : 'password'" name="new_password" x-model="newPassword" required minlength="8" class="input" style="width: 100%; padding-right: 40px;">
                            <button type="button" @click="showNew = !showNew" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--fg-3);">
                                <svg x-show="!showNew" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showNew" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" x-cloak><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a2 2 0 1 1-2.83-2.83M1 1l22 22"/></svg>
                            </button>
                        </div>
                        @error('new_password')
                            <div style="color: var(--status-conflict-fg); font-size: 12px; margin-top: 4px; font-weight: 600;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label>ยืนยันรหัสผ่านใหม่ <span style="color: var(--status-conflict-fg);">*</span></label>
                        <div style="position: relative;">
                            <input :type="showConfirm ? 'text' : 'password'" name="new_password_confirmation" x-model="newPasswordConfirmation" required minlength="8" class="input" style="width: 100%; padding-right: 40px;">
                            <button type="button" @click="showConfirm = !showConfirm" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--fg-3);">
                                <svg x-show="!showConfirm" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showConfirm" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" x-cloak><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a2 2 0 1 1-2.83-2.83M1 1l22 22"/></svg>
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border); padding-top: 20px;">
                        <button type="button" class="btn btn-ghost" @click="close">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" :disabled="!currentPassword || !newPassword || !newPasswordConfirmation || newPassword !== newPasswordConfirmation" :style="(!currentPassword || !newPassword || !newPasswordConfirmation || newPassword !== newPasswordConfirmation) ? 'opacity: 0.5; cursor: not-allowed;' : ''">
                            บันทึกรหัสผ่านใหม่
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>