setTimeout(function() {
    const form = document.querySelector('.footer-form');
    if (!form) {
        console.log('Форма не найдена');
        return;
    }

    // Добавляем поле message, если его нет
    if (!form.querySelector('textarea[name="message"]') && !form.querySelector('input[name="message"]')) {
        const messageField = document.createElement('textarea');
        messageField.name = 'message';
        messageField.style.display = 'none';
        messageField.value = 'Сообщение отправлено через форму обратной связи';
        form.appendChild(messageField);
        console.log('Добавлено поле message');
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const nameInput = form.querySelector('input[name="name"]');
        const phoneInput = form.querySelector('input[name="phone"]');
        const emailInput = form.querySelector('input[name="email"]');
        const messageInput = form.querySelector('textarea[name="message"]');
        
        const data = {
            name: nameInput ? nameInput.value : '',
            phone: phoneInput ? phoneInput.value : '',
            email: emailInput ? emailInput.value : '',
            message: messageInput ? messageInput.value : ''
        };

        try {
            const response = await fetch('/feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                alert('Сообщение отправлено!');
                if (nameInput) nameInput.value = '';
                if (phoneInput) phoneInput.value = '';
                if (emailInput) emailInput.value = '';
                if (messageInput) messageInput.value = '';
            } else {
                alert('Ошибка: ' + JSON.stringify(result.errors || result));
            }
        } catch (err) {
            console.error(err);
            alert('Ошибка сети');
        }
    });
}, 1000);
