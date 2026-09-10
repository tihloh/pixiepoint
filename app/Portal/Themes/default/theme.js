document.documentElement.classList.add('pixiepoint-theme-ready');

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
