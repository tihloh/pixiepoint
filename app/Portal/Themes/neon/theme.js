document.documentElement.classList.add('pixiepoint-neon-theme');

(function(){
    const qr=document.getElementById('pp-qr-scan');
    const voucher=document.getElementById('compat-voucher');
    if(qr&&voucher){
        qr.className='btn btn-outline-secondary';
        qr.setAttribute('aria-label','Scan QR code');
        qr.setAttribute('title','Scan QR code');
        qr.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>';
        voucher.insertAdjacentElement('afterend',qr);
    }
})();

(function(){
    const modal=document.getElementById('pp-member-login-modal');
    if(!modal)return;
    const body=modal.querySelector('.modal-body');
    if(!body)return;
    body.innerHTML=`
        <ul class="nav nav-pills nav-fill mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pp-member-login-pane" type="button">Login</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pp-member-register-pane" type="button">Register</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="pp-member-login-pane">
                <div class="mb-3"><label class="form-label">Email</label><input class="form-control" id="pp-member-identity" type="email" autocomplete="username"></div>
                <div class="mb-3"><label class="form-label">Password</label><input class="form-control" id="pp-member-password" type="password" autocomplete="current-password"></div>
                <button class="btn btn-primary w-100" id="pp-member-submit" type="button" data-feature-action="member-login">Login</button>
                <div class="text-center text-body-secondary small my-3">or</div>
                <button class="btn btn-outline-secondary w-100" type="button" data-feature-action="member-google-login">Continue with Google</button>
            </div>
            <div class="tab-pane fade" id="pp-member-register-pane">
                <div class="mb-3"><label class="form-label">Name</label><input class="form-control" id="pp-member-register-name" autocomplete="name"></div>
                <div class="mb-3"><label class="form-label">Email</label><input class="form-control" id="pp-member-register-email" type="email" autocomplete="email"></div>
                <div class="mb-3"><label class="form-label">Password</label><input class="form-control" id="pp-member-register-password" type="password" autocomplete="new-password"></div>
                <button class="btn btn-primary w-100" id="pp-member-register-submit" type="button" data-feature-action="member-register">Create account</button>
                <div class="text-center text-body-secondary small my-3">or</div>
                <button class="btn btn-outline-secondary w-100" type="button" data-feature-action="member-google-register">Register with Google</button>
            </div>
        </div>`;
})();
