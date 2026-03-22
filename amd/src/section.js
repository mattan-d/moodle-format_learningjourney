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
 * Section highlight UI for this format.
 *
 * @module     format_learningjourney/section
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import Templates from 'core/templates';

class HighlightSection extends BaseComponent {

    create() {
        this.name = 'format_learningjourney_section';
        this.selectors = {
            SETMARKER: `[data-action="sectionHighlight"]`,
            REMOVEMARKER: `[data-action="sectionUnhighlight"]`,
            ACTIONTEXT: `.menu-action-text`,
            ICON: `.icon`,
        };
        this.classes = {
            HIDE: 'd-none',
        };
        this.formatActions = {
            HIGHLIGHT: 'sectionHighlight',
            UNHIGHLIGHT: 'sectionUnhighlight',
        };
    }

    getWatchers() {
        return [
            {watch: `section.current:updated`, handler: this._refreshHighlight},
        ];
    }

    async _refreshHighlight({element}) {
        let selector;
        let newAction;
        if (element.current) {
            selector = this.selectors.SETMARKER;
            newAction = this.formatActions.UNHIGHLIGHT;
        } else {
            selector = this.selectors.REMOVEMARKER;
            newAction = this.formatActions.HIGHLIGHT;
        }
        const affectedAction = this.getElement(`${selector}`, element.id);
        if (!affectedAction) {
            return;
        }
        affectedAction.dataset.action = newAction;
        const actionText = affectedAction.querySelector(this.selectors.ACTIONTEXT);
        if (affectedAction.dataset?.swapname && actionText) {
            const oldText = actionText?.innerText;
            actionText.innerText = affectedAction.dataset.swapname;
            affectedAction.dataset.swapname = oldText;
        }
        const icon = affectedAction.querySelector(this.selectors.ICON);
        if (affectedAction.dataset?.swapicon && icon) {
            const newIcon = affectedAction.dataset.swapicon;
            if (newIcon) {
                const pixHtml = await Templates.renderPix(newIcon, 'core');
                Templates.replaceNode(icon, pixHtml, '');
                affectedAction.dataset.swapicon = affectedAction.dataset.icon;
                affectedAction.dataset.icon = newIcon;
            }
        }
    }
}

export const init = () => {
    const courseEditor = getCurrentCourseEditor();
    if (courseEditor.supportComponents && courseEditor.isEditing) {
        new HighlightSection({
            element: document.getElementById('page'),
            reactive: courseEditor,
        });
    }
};
