import { Controller } from '@hotwired/stimulus';

/*
 * Drag & drop Kanban. Cards carry their move URL + CSRF token as data-attributes;
 * dropping a card on a column POSTs the new status and moves the DOM node on success.
 */
export default class extends Controller {
    connect() {
        this.dragged = null;
    }

    start(event) {
        this.dragged = event.currentTarget;
        event.dataTransfer.effectAllowed = 'move';
    }

    over(event) {
        event.preventDefault();
        event.currentTarget.classList.add('is-over');
    }

    leave(event) {
        event.currentTarget.classList.remove('is-over');
    }

    async drop(event) {
        event.preventDefault();
        const column = event.currentTarget;
        column.classList.remove('is-over');
        const card = this.dragged;
        this.dragged = null;
        if (!card) return;

        const body = new URLSearchParams({
            status: column.dataset.status,
            _token: card.dataset.token,
        });

        const response = await fetch(card.dataset.moveUrl, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.ok) {
            (column.querySelector('[data-kanban-body]') || column).appendChild(card);
        }
    }
}
