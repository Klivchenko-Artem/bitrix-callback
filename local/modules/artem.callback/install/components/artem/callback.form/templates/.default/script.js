/**
 * Отправка формы обратного звонка ajax-действием компонента.
 *
 * Ошибки приходят массивом {message, code}, где code — имя поля,
 * поэтому подсветить нужную строку можно без отдельного формата ответа.
 */
(function () {
    'use strict';

    function ArtemCallbackForm(options) {
        this.container = options.container;
        this.signedParameters = options.signedParameters;
        this.form = this.container.querySelector('.artem-callback__form');
        this.success = this.container.querySelector('.artem-callback__success');
        this.button = this.container.querySelector('.artem-callback__submit');

        this.form.addEventListener('submit', this.onSubmit.bind(this));
    }

    ArtemCallbackForm.prototype.onSubmit = function (event) {
        event.preventDefault();

        if (this.button.disabled) {
            return;
        }

        this.clearErrors();
        this.button.disabled = true;

        BX.ajax.runComponentAction('artem:callback.form', 'submit', {
            mode: 'class',
            signedParameters: this.signedParameters,
            data: {
                fields: this.collect()
            }
        }).then(
            this.onSuccess.bind(this),
            this.onError.bind(this)
        );
    };

    ArtemCallbackForm.prototype.collect = function () {
        var data = {pageUrl: window.location.href};

        ['name', 'phone', 'comment', 'slot'].forEach(function (field) {
            var input = this.form.elements[field];
            data[field] = input ? input.value : '';
        }, this);

        var consent = this.form.elements.consent;
        data.consent = consent && consent.checked ? '1' : '';

        return data;
    };

    ArtemCallbackForm.prototype.onSuccess = function () {
        this.form.hidden = true;
        this.success.hidden = false;
    };

    ArtemCallbackForm.prototype.onError = function (response) {
        this.button.disabled = false;

        var errors = (response && response.errors) || [];

        if (!errors.length) {
            this.showError('common', 'Что-то пошло не так. Попробуйте ещё раз.');
            return;
        }

        errors.forEach(function (error) {
            this.showError(error.code, error.message);
        }, this);
    };

    ArtemCallbackForm.prototype.showError = function (code, message) {
        var target = this.container.querySelector('[data-error="' + code + '"]')
            || this.container.querySelector('[data-error="common"]');

        if (target) {
            target.textContent = message;
            target.classList.add('artem-callback__error--visible');
        }
    };

    ArtemCallbackForm.prototype.clearErrors = function () {
        this.container.querySelectorAll('[data-error]').forEach(function (node) {
            node.textContent = '';
            node.classList.remove('artem-callback__error--visible');
        });
    };

    window.ArtemCallbackForm = ArtemCallbackForm;
})();
