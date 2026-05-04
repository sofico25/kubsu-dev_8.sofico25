// Этот файл нужен только для обработки анкеты (если анкета загружена на странице)
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#anketa-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const data = {
            full_name: formData.get('full_name'),
            phone: formData.get('phone'),
            email: formData.get('email'),
            birth_date: formData.get('birth_date'),
            work_area: formData.get('work_area'),
            positions: formData.getAll('positions'),
            bio: formData.get('bio'),
            contract: formData.has('contract') ? 'on' : null
        };

        try {
            let response = await fetch('/api/entry', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                let errorData = await response.json();
                alert('Ошибка: ' + JSON.stringify(errorData.errors));
                return;
            }

            let result = await response.json();
            if (result.status === 'created') {
                alert(`Логин: ${result.login}\nПароль: ${result.password}\nПрофиль: ${result.profile_url}`);
                form.reset();
            } else if (result.status === 'updated') {
                alert('Данные обновлены');
            }
        } catch (err) {
            alert('Ошибка сети');
        }
    });
});
