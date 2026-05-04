document.addEventListener('DOMContentLoaded', function() {
    // Функция для плавной прокрутки к элементу
    function scrollToElement(element) {
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Находим кнопки навигации
    const navItems = document.querySelectorAll('nav a, .nav-link, .menu-item, header a, button');
    
    // Соответствие между текстом кнопки и ID или классом секции
    const sectionsMap = {
        'ГЛАВНАЯ': 'hero',
        'ДОСТИЖЕНИЯ': 'achievements',
        'БОЛИД SF-25': 'car',
        'ПИЛОТЫ': 'drivers',
        'ТРАССЫ': 'tracks',
        'НОВОСТИ': 'news',
        'ПАРТНЁРЫ': 'partners',
        'Q&A': 'faq',
        'КОНТАКТЫ': 'contacts'
    };

    for (let item of navItems) {
        const text = item.textContent.trim().toUpperCase();
        if (sectionsMap[text]) {
            item.addEventListener('click', function(event) {
                event.preventDefault();
                const targetId = sectionsMap[text];
                let targetElement = document.getElementById(targetId);
                
                // Если элемент с ID не найден, ищем по классу или тексту
                if (!targetElement) {
                    targetElement = document.querySelector(`section.${targetId}, div.${targetId}, [class*="${targetId}"]`);
                }
                
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    console.warn(`Секция "${targetId}" не найдена`);
                }
            });
        }
    }
});
