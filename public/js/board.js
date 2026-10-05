/*
 * A project's board (omnibase/forge, templates/client/project/_board.html.twig): the cards are
 * dragged between the columns - or moved with the arrow keys, a card focused - and each move is
 * saved at once: PATCH <card's data-url> {column, position}, the board's data-token in
 * X-CSRF-Token. A move the server refuses (not one's project, the network down) puts the card
 * back and says so (the board's data-error-message).
 *
 * No dependency and nothing to call: every [data-forge-board] with a data-token is taken in
 * charge, the ones a page brings in later too (a page swapped in without a reload). A board
 * without a token is read-only and left alone.
 */
(function () {
    'use strict';

    if (window.ForgeBoard) return;

    const CARD = '[data-forge-board-card]';
    const COLUMN = '[data-forge-board-column]';

    function bind(board) {
        if (board.forgeBoard || !board.dataset.token) return;
        board.forgeBoard = true;

        const columns = () => [...board.querySelectorAll(COLUMN)];
        const cardsOf = (column) => [...column.querySelectorAll(CARD)];
        let dragged = null;
        let origin = null;

        const recount = () => columns().forEach((column) => {
            const count = column.querySelector('[data-forge-board-count]');
            if (count) count.textContent = cardsOf(column).length;
        });

        const restyle = (card, column) => {
            columns().forEach((each) => card.classList.remove('is-' + each.dataset.forgeBoardColumn));
            card.classList.add('is-' + column.dataset.forgeBoardColumn);
        };

        const fail = () => {
            const message = board.dataset.errorMessage || 'Move not saved.';
            board.dispatchEvent(new CustomEvent('forge-board:error', { bubbles: true, detail: { message } }));
            if (window.Swal && window.Swal.fire) {
                window.Swal.fire({ icon: 'error', text: message, timer: 2500, showConfirmButton: false });
            } else {
                let status = board.querySelector('.forge-board-status');
                if (!status) {
                    status = document.createElement('p');
                    status.className = 'forge-board-status';
                    status.setAttribute('role', 'alert');
                    board.appendChild(status);
                }
                status.textContent = message;
            }
        };

        /* Where the card stands now is what is saved; refused, it goes back where it was. */
        const save = async (card, from) => {
            const column = card.closest(COLUMN);
            const position = cardsOf(column).indexOf(card);
            restyle(card, column);
            recount();
            try {
                const response = await fetch(card.dataset.url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': board.dataset.token },
                    body: JSON.stringify({ column: column.dataset.forgeBoardColumn, position }),
                });
                if (!response.ok) throw new Error(String(response.status));
                board.dispatchEvent(new CustomEvent('forge-board:moved', { bubbles: true, detail: { card: card.dataset.forgeBoardCard, column: column.dataset.forgeBoardColumn, position } }));
            } catch (error) {
                from.column.insertBefore(card, from.next && from.next.parentElement === from.column ? from.next : null);
                restyle(card, from.column);
                recount();
                fail();
            }
        };

        const cardAfter = (column, y) => cardsOf(column).find((card) => {
            if (card === dragged) return false;
            const box = card.getBoundingClientRect();

            return y < box.top + box.height / 2;
        }) || null;

        const prepare = (card) => {
            if (card.forgeBoardCard || !card.dataset.url) return;
            card.forgeBoardCard = true;
            card.setAttribute('draggable', 'true');
            card.setAttribute('tabindex', '0');
        };
        board.querySelectorAll(CARD).forEach(prepare);

        board.addEventListener('dragstart', (event) => {
            const card = event.target.closest && event.target.closest(CARD);
            if (!card || !card.dataset.url) return;
            dragged = card;
            origin = { column: card.closest(COLUMN), next: card.nextElementSibling };
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.forgeBoardCard);
        });

        board.addEventListener('dragend', () => {
            if (dragged) dragged.classList.remove('is-dragging');
            // Dropped outside any column: back where it came from.
            if (dragged && origin) origin.column.insertBefore(dragged, origin.next && origin.next.parentElement === origin.column ? origin.next : null);
            dragged = origin = null;
            columns().forEach((column) => column.classList.remove('is-over'));
            recount();
        });

        board.addEventListener('dragover', (event) => {
            const column = event.target.closest && event.target.closest(COLUMN);
            if (!dragged || !column) return;
            event.preventDefault();
            columns().forEach((each) => each.classList.toggle('is-over', each === column));
            column.insertBefore(dragged, cardAfter(column, event.clientY));
        });

        board.addEventListener('drop', (event) => {
            const column = event.target.closest && event.target.closest(COLUMN);
            if (!dragged || !column) return;
            event.preventDefault();
            const card = dragged;
            const from = origin;
            dragged = origin = null;
            card.classList.remove('is-dragging');
            columns().forEach((each) => each.classList.remove('is-over'));
            save(card, from);
        });

        /* Without a mouse: the arrows move the focused card, up and down its column, left and right to the next. */
        board.addEventListener('keydown', (event) => {
            const card = event.target.closest && event.target.closest(CARD);
            if (!card || !card.dataset.url || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey) return;
            const column = card.closest(COLUMN);
            const from = { column, next: card.nextElementSibling };
            const all = columns();
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                const target = all[all.indexOf(column) + (event.key === 'ArrowRight' ? 1 : -1)];
                if (!target) return;
                target.appendChild(card);
            } else if (event.key === 'ArrowUp') {
                const previous = card.previousElementSibling;
                if (!previous || !previous.matches(CARD)) return;
                column.insertBefore(card, previous);
            } else {
                const next = card.nextElementSibling;
                if (!next) return;
                column.insertBefore(next, card);
            }
            event.preventDefault();
            card.focus();
            save(card, from);
        });
    }

    function scan(root) {
        if (root.matches && root.matches('[data-forge-board]')) bind(root);
        if (root.querySelectorAll) root.querySelectorAll('[data-forge-board]').forEach(bind);
    }

    window.ForgeBoard = { bind, scan };

    const start = () => {
        scan(document);
        new MutationObserver((mutations) => mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) scan(node);
        }))).observe(document.documentElement, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
