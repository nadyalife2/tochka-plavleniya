/**
 * ui-helpers.js — Общий слой UI-помощников для интерактивов верстака «ТОЧКА ПЛАВЛЕНИЯ»
 *
 * Предоставляет единые методы:
 * - formatTemperature(celsius)
 * - showFieldError(inputElement, message)
 * - clearFieldError(inputElement)
 * - showWorkbenchResult(container, options)
 * - copyToClipboard(text, triggerBtn, successText)
 */

(function(window) {
  'use strict';

  const TCHP_UI = {
    /**
     * Форматирование температуры в единый стандартный вид
     * @param {number|string} celsius
     * @returns {string}
     */
    formatTemperature(celsius) {
      if (celsius === null || celsius === undefined || celsius === '') return '—';
      const val = typeof celsius === 'number' ? Math.round(celsius) : String(celsius).trim();
      return `${val} °C`;
    },

    /**
     * Показ ошибки валидации поля без браузерного alert()
     * @param {HTMLElement} inputElement
     * @param {string} message
     */
    showFieldError(inputElement, message) {
      if (!inputElement) return;
      inputElement.classList.add('has-error');

      // Ищем или создаем контейнер сообщения об ошибке рядом с полем
      const parent = inputElement.parentElement;
      let errorEl = parent.querySelector('.field-error-msg');
      if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'field-error-msg text-xs font-mono text-red-600 dark:text-red-400 mt-1 flex items-center gap-1';
        parent.appendChild(errorEl);
      }
      errorEl.innerHTML = `<span class="material-symbols-outlined text-[14px]">error</span> ${message}`;
      inputElement.focus();
    },

    /**
     * Очистка ошибки поля
     * @param {HTMLElement} inputElement
     */
    clearFieldError(inputElement) {
      if (!inputElement) return;
      inputElement.classList.remove('has-error');
      const parent = inputElement.parentElement;
      const errorEl = parent.querySelector('.field-error-msg');
      if (errorEl) {
        errorEl.remove();
      }
    },

    /**
     * Рендеринг структурированного результата в карточку верстака
     * @param {HTMLElement} container
     * @param {Object} options
     *   - title: string
     *   - value: string|number
     *   - unit?: string
     *   - badge?: string
     *   - advice?: string
     *   - tip?: string
     *   - warning?: string
     *   - level?: 'success'|'warning'|'danger'|'info'
     */
    showWorkbenchResult(container, options = {}) {
      if (!container) return;
      const {
        title = 'Результат расчета',
        value = '',
        unit = '',
        badge = '',
        advice = '',
        tip = '',
        warning = '',
        level = 'info'
      } = options;

      let badgeHtml = '';
      if (badge) {
        badgeHtml = `<span class="badge badge-neutral">${badge}</span>`;
      }

      let warningHtml = '';
      if (warning) {
        warningHtml = `
          <div class="alert alert-warning mt-3">
            <span class="material-symbols-outlined text-[18px] text-amber-600 shrink-0">warning</span>
            <div class="text-xs font-mono">${warning}</div>
          </div>
        `;
      }

      let tipHtml = '';
      if (tip) {
        tipHtml = `
          <div class="p-3 bg-paper rounded border border-paper-border text-xs font-mono space-y-1">
            <span class="text-ink-faint uppercase font-semibold">Рекомендация по оснастке:</span>
            <div class="text-ink font-medium">${tip}</div>
          </div>
        `;
      }

      let adviceHtml = '';
      if (advice) {
        adviceHtml = `
          <div class="text-xs font-mono text-ink-muted leading-relaxed pt-2 border-t border-paper-border">
            ${advice}
          </div>
        `;
      }

      container.innerHTML = `
        <div class="card p-5 space-y-4 animate-fade-in">
          <div class="flex items-center justify-between gap-2 pb-2 border-b border-paper-border">
            <span class="text-xs font-mono uppercase font-bold text-ink">${title}</span>
            ${badgeHtml}
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl sm:text-4xl font-mono font-extrabold text-ink">${value}</span>
            ${unit ? `<span class="text-base font-mono text-ink-muted">${unit}</span>` : ''}
          </div>
          ${tipHtml}
          ${adviceHtml}
          ${warningHtml}
        </div>
      `;
    },

    /**
     * Безопасное копирование текста в буфер с визуальной обратной связью
     * @param {string} text
     * @param {HTMLElement} triggerBtn
     * @param {string} successText
     */
    copyToClipboard(text, triggerBtn, successText = 'Скопировано!') {
      if (!navigator.clipboard) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
          document.execCommand('copy');
        } catch (err) {
          console.error('Clipboard copy failed', err);
        }
        document.body.removeChild(textarea);
      } else {
        navigator.clipboard.writeText(text).catch(err => {
          console.error('Clipboard writeText failed', err);
        });
      }

      if (triggerBtn) {
        const originalHtml = triggerBtn.innerHTML;
        triggerBtn.innerHTML = `<span class="text-accent font-semibold">✓ ${successText}</span>`;
        triggerBtn.disabled = true;
        setTimeout(() => {
          triggerBtn.innerHTML = originalHtml;
          triggerBtn.disabled = false;
        }, 1800);
      }
    }
  };

  window.TCHP_UI = TCHP_UI;
})(window);
