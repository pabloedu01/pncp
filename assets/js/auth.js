// assets/js/auth.js

document.addEventListener('DOMContentLoaded', () => {
    // Se já estiver logado, vai pro dashboard
    if (localStorage.getItem('token')) {
        window.location.href = '/dashboard';
        return;
    }

    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const tfaVerifyForm = document.getElementById('tfa-verify-form');
    const forgotPasswordForm = document.getElementById('forgot-password-form');
    const resetPasswordForm = document.getElementById('reset-password-form');
    const lost2faForm = document.getElementById('lost-2fa-form');
    const resetLost2faForm = document.getElementById('reset-lost-2fa-form');
    
    const showRegisterBtn = document.getElementById('show-register');
    const showLoginLinks = document.querySelectorAll('.show-login-link');
    const showForgotPasswordBtn = document.getElementById('show-forgot-password');
    const showLost2faBtn = document.getElementById('show-lost-2fa');
    const btnTfaDone = document.getElementById('btn-tfa-done');
    
    const alertBox = document.getElementById('alert-box');
    const subtitle = document.getElementById('form-subtitle');

    let tempToken = '';

    const showAlert = (msg, isError = true) => {
        alertBox.textContent = msg;
        if (isError) {
            alertBox.className = 'alert alert-error';
            alertBox.style = '';
        } else {
            alertBox.className = 'alert';
            alertBox.style.backgroundColor = '#dcfce7';
            alertBox.style.color = '#166534';
            alertBox.style.borderColor = '#86efac';
        }
        alertBox.classList.remove('hidden');
    };

    const hideAlert = () => {
        alertBox.classList.add('hidden');
    };

    // Alternar Telas
    const hideAllForms = () => {
        loginForm.classList.add('hidden');
        registerForm.classList.add('hidden');
        forgotPasswordForm.classList.add('hidden');
        resetPasswordForm.classList.add('hidden');
        lost2faForm.classList.add('hidden');
        resetLost2faForm.classList.add('hidden');
        document.getElementById('tfa-verify-container').classList.add('hidden');
        document.getElementById('tfa-setup-container').classList.add('hidden');
    };

    showRegisterBtn.addEventListener('click', (e) => {
        e.preventDefault();
        hideAlert();
        hideAllForms();
        registerForm.classList.remove('hidden');
        subtitle.textContent = "Crie sua conta para acessar";
    });

    showLoginLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            hideAlert();
            hideAllForms();
            loginForm.classList.remove('hidden');
            subtitle.textContent = "Faça login para continuar";
        });
    });

    showForgotPasswordBtn.addEventListener('click', (e) => {
        e.preventDefault();
        hideAlert();
        hideAllForms();
        forgotPasswordForm.classList.remove('hidden');
        subtitle.textContent = "Recuperar Senha";
    });

    showLost2faBtn.addEventListener('click', (e) => {
        e.preventDefault();
        hideAlert();
        hideAllForms();
        lost2faForm.classList.remove('hidden');
        subtitle.textContent = "Recuperar Autenticador";
    });

    btnTfaDone.addEventListener('click', () => {
        document.getElementById('tfa-setup-container').classList.add('hidden');
        loginForm.classList.remove('hidden');
        subtitle.textContent = "Faça login para continuar";
    });

    // Formatar CPF
    const cpfInput = document.getElementById('reg-cpf');
    cpfInput.addEventListener('input', function() {
        let val = this.value.replace(/\D/g, '');
        if (val.length > 11) val = val.slice(0, 11);
        if (val.length > 9) val = val.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        else if (val.length > 6) val = val.replace(/(\d{3})(\d{3})(\d{3})/, "$1.$2.$3");
        else if (val.length > 3) val = val.replace(/(\d{3})(\d{3})/, "$1.$2");
        this.value = val;
    });

    // Submeter Cadastro
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const email = document.getElementById('reg-email').value;
        const cpf = cpfInput.value;
        const password = document.getElementById('reg-password').value;

        try {
            const data = await api.post('/auth/register', { email, cpf, password });
            
            // Mostrar Setup do 2FA
            registerForm.classList.add('hidden');
            document.getElementById('tfa-setup-container').classList.remove('hidden');
            document.getElementById('tfa-qrcode').src = data.qr_code;
            document.getElementById('tfa-secret').textContent = data.secret;
            subtitle.textContent = "Configure seu 2FA";

        } catch (error) {
            showAlert(error.message);
        }
    });

    // Submeter Login
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;

        try {
            const data = await api.post('/auth/login', { email, password });
            if (data.setup_2fa) {
                loginForm.classList.add('hidden');
                document.getElementById('tfa-setup-container').classList.remove('hidden');
                document.getElementById('tfa-qrcode').src = data.qr_code;
                document.getElementById('tfa-secret').textContent = data.secret;
                subtitle.textContent = "Configure seu 2FA Novamente";
            } else if (data.require_2fa) {
                tempToken = data.temp_token;
                loginForm.classList.add('hidden');
                document.getElementById('tfa-verify-container').classList.remove('hidden');
                subtitle.textContent = "Verificação 2FA";
            }
        } catch (error) {
            showAlert(error.message);
        }
    });

    // Submeter 2FA
    tfaVerifyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const code = document.getElementById('tfa-code').value;

        try {
            const data = await api.post('/auth/verify-2fa', { code, temp_token: tempToken });
            localStorage.setItem('token', data.token);
            window.location.href = '/dashboard';
        } catch (error) {
            showAlert(error.message);
        }
    });

    // Submeter Esqueci a Senha
    forgotPasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const email = document.getElementById('forgot-email').value;

        try {
            const data = await api.post('/auth/forgot-password', { email });
            showAlert(data.message, false);
            
            setTimeout(() => {
                hideAllForms();
                resetPasswordForm.classList.remove('hidden');
                subtitle.textContent = "Redefinir Senha";
                hideAlert();
            }, 3000);
        } catch (error) {
            showAlert(error.message);
        }
    });

    // Submeter Redefinir Senha
    resetPasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const token = document.getElementById('reset-token').value;
        const password = document.getElementById('reset-password').value;

        try {
            const data = await api.post('/auth/reset-password', { token, password });
            showAlert(data.message, false);
            setTimeout(() => {
                hideAllForms();
                loginForm.classList.remove('hidden');
                subtitle.textContent = "Faça login para continuar";
                hideAlert();
            }, 3000);
        } catch (error) {
            showAlert(error.message);
        }
    });

    // Submeter Perda de 2FA
    lost2faForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        try {
            const data = await api.post('/auth/lost-2fa', { temp_token: tempToken });
            showAlert(data.message, false);
            
            setTimeout(() => {
                hideAllForms();
                resetLost2faForm.classList.remove('hidden');
                subtitle.textContent = "Validar Código";
                hideAlert();
            }, 3000);
        } catch (error) {
            showAlert(error.message);
        }
    });

    // Submeter Validação do Código 2FA Perdido
    resetLost2faForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        const code = document.getElementById('reset-2fa-code').value;

        try {
            const data = await api.post('/auth/reset-lost-2fa', { temp_token: tempToken, code });
            showAlert(data.message, false);
            
            setTimeout(() => {
                hideAllForms();
                document.getElementById('tfa-setup-container').classList.remove('hidden');
                document.getElementById('tfa-qrcode').src = data.qr_code;
                document.getElementById('tfa-secret').textContent = data.secret;
                subtitle.textContent = "Configure seu 2FA Novamente";
                hideAlert();
            }, 3000);
        } catch (error) {
            showAlert(error.message);
        }
    });
});
