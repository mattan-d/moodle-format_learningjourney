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
 * Learning journey format mutations (section highlight).
 *
 * @module     format_learningjourney/mutations
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import DefaultMutations from 'core_courseformat/local/courseeditor/mutations';
import CourseActions from 'core_courseformat/local/content/actions';

class LearningJourneyMutations extends DefaultMutations {

    sectionHighlight = async function(stateManager, sectionIds) {
        const logEntry = this._getLoggerEntry(
            stateManager,
            'section_highlight',
            sectionIds,
            {component: 'format_learningjourney'}
        );
        const course = stateManager.get('course');
        this.sectionLock(stateManager, sectionIds, true);
        const updates = await this._callEditWebservice('section_highlight', course.id, sectionIds);
        stateManager.processUpdates(updates);
        this.sectionLock(stateManager, sectionIds, false);
        stateManager.addLoggerEntry(await logEntry);
    };

    sectionUnhighlight = async function(stateManager, sectionIds) {
        const logEntry = this._getLoggerEntry(
            stateManager,
            'section_unhighlight',
            sectionIds,
            {component: 'format_learningjourney'}
        );
        const course = stateManager.get('course');
        this.sectionLock(stateManager, sectionIds, true);
        const updates = await this._callEditWebservice('section_unhighlight', course.id, sectionIds);
        stateManager.processUpdates(updates);
        this.sectionLock(stateManager, sectionIds, false);
        stateManager.addLoggerEntry(await logEntry);
    };
}

export const init = () => {
    const courseEditor = getCurrentCourseEditor();
    courseEditor.addMutations(new LearningJourneyMutations());
    CourseActions.addActions({
        sectionHighlight: 'sectionHighlight',
        sectionUnhighlight: 'sectionUnhighlight',
    });
};
