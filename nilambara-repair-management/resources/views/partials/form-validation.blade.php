<script>
    (() => {
        const errors = @js($errors->messages());
        let firstInvalid;
        for (const [key, messages] of Object.entries(errors)) {
            const segments = key.split('.');
            const name = segments.shift() + segments.map(segment => `[${segment}]`).join('');
            const field = Array.from(document.querySelectorAll('input, select, textarea')).find(item => item.name === name && item.type !== 'hidden' && !item.disabled);
            if (!field) continue;
            field.setAttribute('aria-invalid', 'true');
            field.classList.add('has-error');
            const error = document.createElement('p');
            error.id = `validation-error-${key.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
            error.className = 'mt-1 text-xs text-red-600';
            error.textContent = messages.join(' ');
            const existing = field.nextElementSibling;
            const message = existing && existing.textContent.trim() === error.textContent ? existing : error;
            if (message === error) field.insertAdjacentElement('afterend', error);
            message.id ||= error.id;
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
            firstInvalid ||= field;
        }
        firstInvalid?.focus();
        for (const form of document.querySelectorAll('form')) {
            form.addEventListener('invalid', event => {
                event.target.setAttribute('aria-invalid', 'true');
                event.target.classList.add('has-error');
            }, true);
            form.addEventListener('input', event => {
                const field = event.target;
                if (field.validity?.valid) {
                    field.removeAttribute('aria-invalid');
                    field.classList.remove('has-error');
                }
            });
        }
    })();
</script>
