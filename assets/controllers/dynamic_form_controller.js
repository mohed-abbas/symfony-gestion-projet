import { Controller } from '@hotwired/stimulus';

/*
 * Re-renders the task form when its `type` select changes, so the
 * type-specific fields (severity / business value / story points) swap live.
 * Posts the current form back to its own action; the controller detects the XHR
 * request and returns just the form partial, which replaces this element's content.
 * An unsubmitted form comes back as 422 (ux-turbo's invalid-form convention) — that's
 * the expected "just re-render" case, so we swap on 2xx and 422 alike.
 */
export default class extends Controller {
    async refresh() {
        const form = this.element.querySelector('form');
        if (!form) return;

        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.ok || response.status === 422) {
            this.element.innerHTML = await response.text();
        }
    }
}
