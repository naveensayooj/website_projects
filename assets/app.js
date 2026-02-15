document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tab-target]').forEach(button => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-tab-target');
            const container = button.closest('[data-tab-container]');
            if (!container) return;
            container.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('[data-tab]').forEach(panel => panel.hidden = true);
            button.classList.add('active');
            const panel = container.querySelector(`[data-tab="${target}"]`);
            if (panel) panel.hidden = false;
        });
    });

    document.querySelectorAll('.rating-input').forEach(wrapper => {
        const input = wrapper.querySelector('input[type="hidden"]');
        wrapper.querySelectorAll('span').forEach(star => {
            star.addEventListener('click', () => {
                const value = parseInt(star.dataset.value, 10);
                input.value = value;
                wrapper.querySelectorAll('span').forEach(s => {
                    s.classList.toggle('active', parseInt(s.dataset.value, 10) <= value);
                });
            });
        });
    });

    document.querySelectorAll('[data-calendar]').forEach(cal => {
        cal.addEventListener('click', e => {
            const slot = e.target.closest('.calendar-slot');
            if (!slot || slot.classList.contains('unavailable')) return;
            cal.querySelectorAll('.calendar-slot').forEach(s => s.classList.remove('selected'));
            slot.classList.add('selected');
            const input = cal.querySelector('input[type="hidden"]');
            if (input) {
                input.value = slot.dataset.value || '';
            }
        });
    });
});

