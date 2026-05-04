// assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = 'index.html';
        return;
    }

    // Decode token to get user info (simple base64 decode for the payload)
    let payload = null;
    try {
        payload = JSON.parse(atob(token.split('.')[1]));
        document.getElementById('user-info').textContent = `${payload.data.email} (${payload.data.role})`;
    } catch (e) {
        // invalid token
        localStorage.removeItem('token');
        window.location.href = 'index.html';
        return;
    }

    if (payload && payload.data.role === 'MASTER') {
        document.getElementById('users-section').classList.remove('hidden');
    }

    const logoutBtn = document.getElementById('btn-logout');
    logoutBtn.addEventListener('click', () => {
        localStorage.removeItem('token');
        window.location.href = 'index.html';
    });

    const empresaForm = document.getElementById('empresa-form');
    const cnpjInput = document.getElementById('emp-cnpj');
    const tableBody = document.querySelector('#empresas-table tbody');
    const alertBox = document.getElementById('alert-box');

    const showAlert = (msg, isError = true) => {
        alertBox.textContent = msg;
        alertBox.className = `alert ${isError ? 'alert-error' : ''}`;
        alertBox.classList.remove('hidden');
    };

    const hideAlert = () => {
        alertBox.classList.add('hidden');
    };

    // Formatar CNPJ
    cnpjInput.addEventListener('input', function() {
        let val = this.value.replace(/\D/g, '');
        if (val.length > 14) val = val.slice(0, 14);
        if (val.length > 12) val = val.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
        else if (val.length > 8) val = val.replace(/(\d{2})(\d{3})(\d{3})(\d{4})/, "$1.$2.$3/$4");
        else if (val.length > 5) val = val.replace(/(\d{2})(\d{3})(\d{3})/, "$1.$2.$3");
        else if (val.length > 2) val = val.replace(/(\d{2})(\d{3})/, "$1.$2");
        this.value = val;
    });

    // Carregar Prefeituras
    const loadEmpresas = async () => {
        try {
            const data = await api.get('/empresas');
            tableBody.innerHTML = '';
            
            if (data.empresas.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhuma prefeitura encontrada.</td></tr>';
                return;
            }

            data.empresas.forEach(emp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${emp.id}</td>
                    <td>${emp.nome}</td>
                    <td>${emp.cnpj}</td>
                    <td>${emp.user_role || 'MASTER'}</td>
                `;
                tableBody.appendChild(tr);
            });
        } catch (error) {
            console.error(error);
        }
    };

    // Submeter Cadastro de Prefeitura
    empresaForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        const nome = document.getElementById('emp-nome').value;
        const cnpj = cnpjInput.value;

        try {
            await api.post('/empresas', { nome, cnpj });
            // Limpa formulário e recarrega lista
            empresaForm.reset();
            loadEmpresas();
        } catch (error) {
            showAlert(error.message);
        }
    });

    // Carregar Usuários
    const loadUsers = async () => {
        try {
            const data = await api.get('/users');
            const tbody = document.querySelector('#users-table tbody');
            tbody.innerHTML = '';
            
            data.users.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${u.id}</td>
                    <td>${u.email}</td>
                    <td>${u.cpf}</td>
                    <td>${u.role}</td>
                    <td>${u.has_2fa ? 'Sim' : 'Não'}</td>
                    <td>
                        ${u.has_2fa ? `<button class="btn btn-outline btn-reset-2fa" data-id="${u.id}" style="height: 2rem; padding: 0 0.5rem; font-size: 0.75rem; border-color: var(--destructive); color: var(--destructive);">Resetar 2FA</button>` : ''}
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.querySelectorAll('.btn-reset-2fa').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    if (confirm('Tem certeza que deseja remover o 2FA deste usuário? Ele terá que configurar novamente no próximo login.')) {
                        try {
                            const res = await api.post('/users/reset-2fa', { user_id: e.target.getAttribute('data-id') });
                            alert(res.message);
                            loadUsers();
                        } catch(err) {
                            alert(err.message);
                        }
                    }
                });
            });
        } catch (error) {
            console.error(error);
        }
    };

    // Init
    loadEmpresas();
    if (payload && payload.data.role === 'MASTER') {
        loadUsers();
    }
});
