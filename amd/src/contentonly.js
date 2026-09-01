// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Content-only header: hamburger course menu toggle.
 *
 * @module     format_learningjourney/contentonly
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    header: '[data-region="lj-contentonly-header"]',
    menu: '[data-region="lj-contentonly-menu"]',
    backdrop: '[data-region="lj-contentonly-backdrop"]',
    toggle: '[data-action="lj-menu-toggle"]',
    close: '[data-action="lj-menu-close"]',
};

const OPEN_CLASS = 'lj-menu-open';

/**
 * @param {boolean} open
 */
const setOpen = (open) => {
    const menu = document.querySelector(SELECTORS.menu);
    const backdrop = document.querySelector(SELECTORS.backdrop);
    const toggle = document.querySelector(SELECTORS.toggle);
    if (!menu || !backdrop || !toggle) {
        return;
    }

    document.body.classList.toggle(OPEN_CLASS, open);
    menu.hidden = !open;
    backdrop.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
};

/**
 * Initialise the content-only hamburger menu.
 */
export const init = () => {
    const header = document.querySelector(SELECTORS.header);
    if (!header || header.dataset.ljContentonlyInit === '1') {
        return;
    }
    header.dataset.ljContentonlyInit = '1';

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest(SELECTORS.toggle);
        if (toggle) {
            event.preventDefault();
            const isOpen = document.body.classList.contains(OPEN_CLASS);
            setOpen(!isOpen);
            return;
        }
        if (event.target.closest(SELECTORS.close)) {
            event.preventDefault();
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains(OPEN_CLASS)) {
            setOpen(false);
        }
    });
};
