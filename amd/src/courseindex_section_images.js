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
 * Injects section banner images into the course index (core template cannot be overridden from a format).
 *
 * @module     format_learningjourney/courseindex_section_images
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';

class CourseIndexSectionImages extends BaseComponent {

    create() {
        this.name = 'format_learningjourney_courseindex_images';
    }

    getWatchers() {
        return [
            {watch: 'section:updated', handler: this._scheduleRefresh},
        ];
    }

    stateReady() {
        this._scheduleRefresh();
        const host = document.getElementById('courseindex') || document.getElementById('course-index');
        if (host && !this._observer) {
            this._observer = new MutationObserver(() => this._scheduleRefresh());
            this._observer.observe(host, {childList: true, subtree: true});
        }
    }

    _scheduleRefresh() {
        if (this._refreshPending) {
            return;
        }
        this._refreshPending = true;
        window.requestAnimationFrame(() => {
            this._refreshPending = false;
            this._refreshImages();
        });
    }

    _refreshImages() {
        const editor = this.reactive;
        if (!editor?.stateManager) {
            return;
        }
        const root = document.getElementById('course-index') || document.getElementById('courseindex');
        if (!root) {
            return;
        }
        root.querySelectorAll('.courseindex-section[data-for="section"]').forEach((sectionEl) => {
            const id = parseInt(sectionEl.getAttribute('data-id'), 10);
            if (!id) {
                return;
            }
            const info = editor.stateManager.get('section', id);
            const existing = sectionEl.querySelector('[data-for="learningjourney_courseindex_image"]');
            if (!info?.hassectionimage || !info.sectionimageurl) {
                existing?.remove();
                return;
            }
            if (existing && existing.dataset.imageurl === info.sectionimageurl) {
                return;
            }
            existing?.remove();
            const wrap = document.createElement('div');
            wrap.className = 'courseindex-section-image px-2 pb-1';
            wrap.dataset.for = 'learningjourney_courseindex_image';
            wrap.dataset.imageurl = info.sectionimageurl;
            const img = document.createElement('img');
            img.src = info.sectionimageurl;
            img.alt = info.sectionimagealt || '';
            img.className = 'img-fluid rounded border w-100';
            img.style.maxHeight = '72px';
            img.style.objectFit = 'cover';
            wrap.appendChild(img);
            const titleRow = sectionEl.querySelector('.courseindex-section-title');
            if (titleRow) {
                titleRow.insertAdjacentElement('afterend', wrap);
            } else {
                sectionEl.prepend(wrap);
            }
        });
    }
}

export const init = () => {
    const attach = () => {
        const editor = getCurrentCourseEditor();
        if (!editor) {
            return false;
        }
        new CourseIndexSectionImages({
            element: document.body,
            reactive: editor,
        });
        return true;
    };
    if (!attach()) {
        window.setTimeout(attach, 500);
    }
};
