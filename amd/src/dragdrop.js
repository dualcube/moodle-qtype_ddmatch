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
 * drag and drop frontend handeling by jquery
 *
 * @package qtype_ddmatch
 * 
 * @author DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube (https://dualcube.com) 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/dragdrop',
    'core/key_codes',
], function(
    $,
    dragDrop,
    keys,
) {

    "use strict";

    /**
     * Object to handle one drag-drop into text question.
     *
     * @param {String} containerId id of the outer div for this question.
     * @param {boolean} readOnly whether the question is being displayed read-only.
     * @constructor
     */
    function DragDropToMatch(containerId, readOnly) {
        this.containerId = containerId;
        this.questionAnswer = {};
        if (readOnly) {
            this.getRoot().addClass('qtype_ddmatch-readonly');
        }
        this.cloneDrags();
        this.positionDrags();
    }

    /**
     * Invisible 'drag homes' are output by the renderer. These have the same properties
     * as the drag items but are invisible. We clone these invisible elements to make the
     * actual drag items.
     */
    DragDropToMatch.prototype.cloneDrags = function() {
        var thisQ = this;
        thisQ.getRoot().find('li.draghome').each(function(index, draghome) {
            var drag = $(draghome);
            var placeHolder = drag.clone();
            placeHolder.removeClass();
            placeHolder.addClass('draghome choice' +
                thisQ.getChoice(drag) + ' dragplaceholder');
            drag.before(placeHolder);
        });
    };

    /**
     * Update the position of drags.
     */
    DragDropToMatch.prototype.positionDrags = function() {
        var thisQ = this,
            root = this.getRoot();

        // First move all items back home.
        root.find('li.draghome').not('.dragplaceholder').each(function(i, dragNode) {
            var drag = $(dragNode),
                currentPlace = thisQ.getClassnameNumericSuffix(drag, 'inplace');
            drag.addClass('unplaced')
                .removeClass('placed');
            drag.removeAttr('tabindex');
            if (currentPlace !== null) {
                drag.removeClass('inplace' + currentPlace);
            }
        });

        // Then place the once that should be placed.
        var choice = [];
        root.find('option[selected=selected]').each((f, option) => {
            choice[f] = option.value;
        });
        root.find('ul.drop').each(function(i, inputNode) {
            var input = $(inputNode),
                place = thisQ.getPlace(input);
            // Record the last known position of the drop.
            var drop = root.find('.drop.place' + place),
                dropPosition = drop.offset();
            drop.data('prev-top', dropPosition.top).data('prev-left', dropPosition.left);

            if (choice[i] === '0') {
                // No item in this place.
                return;
            }

            // Get the unplaced drag.
            var unplacedDrag = thisQ.getUnplacedChoice(choice[i]);
            // Get the clone of the drag.
            var hiddenDrag = thisQ.getDragClone(unplacedDrag);
            if (hiddenDrag.length) {
                if (unplacedDrag.hasClass('infinite')) {
                    var noOfDrags = thisQ.noOfDropsIn();
                    var cloneDrags = thisQ.getInfiniteDragClones(unplacedDrag, false);
                    if (cloneDrags.length < noOfDrags) {
                        var cloneDrag = unplacedDrag.clone();
                        hiddenDrag.after(cloneDrag);
                        questionManager.addEventHandlersToDrag(cloneDrag);
                    } else {
                        hiddenDrag.addClass('active');
                    }
                } else {
                    hiddenDrag.addClass('active');
                }
            }
            // Send the drag to drop.
            thisQ.sendDragToDrop(thisQ.getUnplacedChoice(choice[i]), drop);
        });

        // Save the question answer.
        thisQ.questionAnswer = thisQ.getQuestionAnsweredValues();
    };

    /**
     * Get the question answered values.
     *
     * @return {Object} Contain key-value with key is the input id and value is the input value.
     */
    DragDropToMatch.prototype.getQuestionAnsweredValues = function() {
        let result = {};
        this.getRoot().find('option[selected=selected]').each((i, option) => {
            result[option.closest('select').name] = option.value;
        });
        return result;
    };

    /**
     * Check if the question is being interacted or not.
     *
     * @return {boolean} Return true if the user has changed the question-answer.
     */
    DragDropToMatch.prototype.isQuestionInteracted = function() {
        const oldAnswer = this.questionAnswer;
        const newAnswer = this.getQuestionAnsweredValues();
        let isInteracted = false;

        // First, check both answers have the same structure or not.
        if (JSON.stringify(newAnswer) !== JSON.stringify(oldAnswer)) {
            isInteracted = true;
            return isInteracted;
        }
        // Check the values.
        Object.keys(newAnswer).forEach(key => {
            if (newAnswer[key] !== oldAnswer[key]) {
                isInteracted = true;
            }
        });

        return isInteracted;
    };

    /**
     * Handles the start of dragging an item.
     *
     * @param {Event} e the touch start or mouse down event.
     */
    DragDropToMatch.prototype.handleDragStart = function(e) {
        var thisQ = this,
            drag = $(e.target).closest('.draghome');

        var info = dragDrop.prepare(e);
        if (!info.start || drag.hasClass('beingdragged')) {
            return;
        }

        drag.addClass('beingdragged');
        var currentPlace = this.getClassnameNumericSuffix(drag, 'inplace');
        var name = drag.parent().find('ul').attr('name');
        if (currentPlace !== null) {
            this.setInputValue(name, 0);
            drag.removeClass('inplace' + currentPlace);
            var hiddenDrop = thisQ.getDrop(drag, currentPlace);
            if (hiddenDrop.length) {
                hiddenDrop.addClass('active');
                drag.offset(hiddenDrop.offset());
            }
        } else {
            var hiddenDrag = thisQ.getDragClone(drag);
            if (hiddenDrag.length) {
                if (drag.hasClass('infinite')) {
                    var noOfDrags = this.noOfDropsIn();
                    var cloneDrags = this.getInfiniteDragClones(drag, false);
                    if (cloneDrags.length < noOfDrags) {
                        var cloneDrag = drag.clone();
                        cloneDrag.removeClass('beingdragged');
                        hiddenDrag.after(cloneDrag);
                        questionManager.addEventHandlersToDrag(cloneDrag);
                        drag.offset(cloneDrag.offset());
                    } else {
                        hiddenDrag.addClass('active');
                        drag.offset(hiddenDrag.offset());
                    }
                } else {
                    hiddenDrag.addClass('active');
                    drag.offset(hiddenDrag.offset());
                }
            }
        }

        dragDrop.start(e, drag, function(x, y, drag) {
            thisQ.dragMove(x, y, drag);
        }, function(x, y, drag) {
            thisQ.dragEnd(x, y, drag);
        });
    };

    /**
     * Called whenever the currently dragged items moves.
     *
     * @param {Number} pageX the x position.
     * @param {Number} pageY the y position.
     * @param {jQuery} drag the item being moved.
     */
    DragDropToMatch.prototype.dragMove = function(pageX, pageY, drag) {
        var thisQ = this;
        this.getRoot().find('ul.drop').each(function(i, dropNode) {
            var drop = $(dropNode);
            if (thisQ.isPointInDrop(pageX, pageY, drop)) {
                drop.addClass('valid-drag-over-drop');
            } else {
                drop.removeClass('valid-drag-over-drop');
            }
        });
        this.getRoot().find('li.draghome.placed').not('.beingdragged').each(function(i, dropNode) {
            var drop = $(dropNode);
            if (thisQ.isPointInDrop(pageX, pageY, drop) && !thisQ.isDragSameAsDrop(drag, drop)) {
                drop.addClass('valid-drag-over-drop');
            } else {
                drop.removeClass('valid-drag-over-drop');
            }
        });
    };

    /**
     * Called when user drops a drag item.
     *
     * @param {Number} pageX the x position.
     * @param {Number} pageY the y position.
     * @param {jQuery} drag the item being moved.
     */
    DragDropToMatch.prototype.dragEnd = function(pageX, pageY, drag) {
        var thisQ = this,
            root = this.getRoot(),
            placed = false;
        root.find('ul.drop').each(function(i, dropNode) {
            var drop = $(dropNode);
            if (!thisQ.isPointInDrop(pageX, pageY, drop)) {
                // Not this drop.
                return true;
            }

            // Now put this drag into the drop.
            drop.removeClass('valid-drag-over-drop');
            thisQ.sendDragToDrop(drag, drop);
            placed = true;
            return false; // Stop the each() here.
        });

        root.find('li.draghome.placed').not('.beingdragged').each(function(i, placedNode) {
            var placedDrag = $(placedNode);
            if (!thisQ.isPointInDrop(pageX, pageY, placedDrag) || thisQ.isDragSameAsDrop(drag, placedDrag)) {
                // Not this placed drag.
                return true;
            }

            // Now put this drag into the drop.
            placedDrag.removeClass('valid-drag-over-drop');
            var currentPlace = thisQ.getClassnameNumericSuffix(placedDrag, 'inplace');
            var drop = thisQ.getDrop(drag, currentPlace);
            thisQ.sendDragToDrop(drag, drop);
            placed = true;
            return false; // Stop the each() here.
        });

        if (!placed) {
            this.sendDragHome(drag);
        }
    };

    /**
     * Animate a drag item into a given place (or back home).
     *
     * @param {jQuery|null} drag the item to place. If null, clear the place.
     * @param {jQuery} drop the place to put it.
     */
    DragDropToMatch.prototype.sendDragToDrop = function(drag, drop) {
        // Is there already a drag in this drop? if so, evict it.
        var oldDrag = this.getCurrentDragInPlace(this.getPlace(drop));
        if (oldDrag.length !== 0) {
            var currentPlace = this.getClassnameNumericSuffix(oldDrag, 'inplace');
            var hiddenDrop = this.getDrop(oldDrag, currentPlace);
            hiddenDrop.addClass('active');
            oldDrag.addClass('beingdragged');
            oldDrag.offset(hiddenDrop.offset());
            this.sendDragHome(oldDrag);
        }

        if (drag.length === 0) {
            this.setInputValue(drop.attr('name'), 0);
        } else {
            this.setInputValue(drop.attr('name'), this.getChoice(drag));
            drag.removeClass('unplaced')
                .addClass('placed inplace' + this.getPlace(drop));
            drag.attr('tabindex', 0);
            this.animateTo(drag, drop);
        }
    };

    /**
     * Animate a drag back to its home.
     *
     * @param {jQuery} drag the item being moved.
     */
    DragDropToMatch.prototype.sendDragHome = function(drag) {
        var currentPlace = this.getClassnameNumericSuffix(drag, 'inplace');
        if (currentPlace !== null) {
            drag.removeClass('inplace' + currentPlace);
        }
        drag.data('unplaced', true);

        this.animateTo(drag, this.getDragHome(this.getChoice(drag)));
    };

    /**
     * Handles keyboard events on drops.
     *
     * Drops are focusable. Once focused, right/down/space switches to the next choice, and
     * left/up switches to the previous. Escape clear.
     *
     * @param {KeyboardEvent} e
     */
    DragDropToMatch.prototype.handleKeyPress = function(e) {
        var drop = $(e.target).closest('.drop');
        if (drop.length === 0) {
            var placedDrag = $(e.target);
            var currentPlace = this.getClassnameNumericSuffix(placedDrag, 'inplace');
            if (currentPlace !== null) {
                drop = this.getDrop(placedDrag, currentPlace);
            }
        }
        var currentDrag = this.getCurrentDragInPlace(this.getPlace(drop)),
            nextDrag = $();

        switch (e.keyCode) {
            case keys.space:
            case keys.arrowRight:
            case keys.arrowDown:
                nextDrag = this.getNextDrag(currentDrag);
                break;

            case keys.arrowLeft:
            case keys.arrowUp:
                nextDrag = this.getPreviousDrag(currentDrag);
                break;

            case keys.escape:
                break;

            default:
                questionManager.isKeyboardNavigation = false;
                return; // To avoid the preventDefault below.
        }

        if (nextDrag.length) {
            nextDrag.addClass('beingdragged');
            var hiddenDrag = this.getDragClone(nextDrag);
            if (hiddenDrag.length) {
                if (nextDrag.hasClass('infinite')) {
                    var noOfDrags = this.noOfDropsIn();
                    var cloneDrags = this.getInfiniteDragClones(nextDrag, false);
                    if (cloneDrags.length < noOfDrags) {
                        var cloneDrag = nextDrag.clone();
                        cloneDrag.removeClass('beingdragged');
                        cloneDrag.removeAttr('tabindex');
                        hiddenDrag.after(cloneDrag);
                        questionManager.addEventHandlersToDrag(cloneDrag);
                        nextDrag.offset(cloneDrag.offset());
                    } else {
                        hiddenDrag.addClass('active');
                        nextDrag.offset(hiddenDrag.offset());
                    }
                } else {
                    hiddenDrag.addClass('active');
                    nextDrag.offset(hiddenDrag.offset());
                }
            }
        }

        e.preventDefault();
        this.sendDragToDrop(nextDrag, drop);
    };

    /**
     * Choose the next drag .
     * @param {jQuery} drag current choice (empty jQuery if there isn't one).
     * @return {jQuery} the next drag , or null if there wasn't one.
     */
    DragDropToMatch.prototype.getNextDrag = function(drag) {
        var choice;

        if (drag.length === 0) {
            choice = 1; // Was empty, so we want to select the first choice.
        } else {
            choice = this.getChoice(drag) + 1;
        }

        var next = this.getUnplacedChoice(choice);
        while (next.length === 0 && choice < numChoices) {
            choice++;
            next = this.getUnplacedChoice(choice);
        }

        return next;
    };

    /**
     * Choose the previous drag.
     *
     * @param {jQuery} drag current choice (empty jQuery if there isn't one).
     * @return {jQuery} the next drag , or null if there wasn't one.
     */
    DragDropToMatch.prototype.getPreviousDrag = function(drag) {
        var choice;

        if (drag.length !== 0) {
            choice = this.getChoice(drag) - 1;
        }

        var previous = this.getUnplacedChoice(choice);
        while (previous.length === 0 && choice > 1) {
            choice--;
            previous = this.getUnplacedChoice(choice);
        }

        // Does this choice exist?
        return previous;
    };

    /**
     * Animate an object to the given destination.
     *
     * @param {jQuery} drag the element to be animated.
     * @param {jQuery} target element marking the place to move it to.
     */
    DragDropToMatch.prototype.animateTo = function(drag, target) {
        var currentPos = drag.offset(),
            targetPos = target.offset(),
            thisQ = this;

        M.util.js_pending('qtype_ddmatch-animate-' + thisQ.containerId);
        // Animate works in terms of CSS position, whereas locating an object
        // on the page works best with jQuery offset() function. So, to get
        // the right target position, we work out the required change in
        // offset() and then add that to the current CSS position.
        drag.animate(
            {
                left: parseInt(drag.css('left')) + targetPos.left - currentPos.left,
                top: parseInt(drag.css('top')) + targetPos.top - currentPos.top
            },
            {
                duration: 'fast',
                done: function() {
                    $('body').trigger('qtype_ddmatch-dragmoved', [drag, target, thisQ]);
                    M.util.js_complete('qtype_ddmatch-animate-' + thisQ.containerId);
                }
            }
        );
    };

    /**
     * Detect if a point is inside a given DOM node.
     *
     * @param {Number} pageX the x position.
     * @param {Number} pageY the y position.
     * @param {jQuery} drop the node to check (typically a drop).
     * @return {boolean} whether the point is inside the node.
     */
    DragDropToMatch.prototype.isPointInDrop = function(pageX, pageY, drop) {
        var position = drop.offset();
        return pageX >= position.left && pageX < position.left + drop.width()
                && pageY >= position.top && pageY < position.top + drop.height();
    };

    /**
     * Set the value of the hidden input for a place, to record what is currently there.
     *
     * @param {int} place which place to set the input value for.
     * @param {int} choice the value to set.
     */
    DragDropToMatch.prototype.setInputValue = function(place, choice) {
        if(place === undefined){
        }else{
            var name = place.replace( /:/g, "\\:" );
            var chosen = this.getRoot().find("#menu"+ name);
            chosen.find('option[selected=selected]').removeAttr('selected');
            chosen.find('option[value=' + choice + ']').attr('selected', 'selected');
        }
        
            
    };

    /**
     * Get the outer div for this question.
     *
     * @returns {jQuery} containing that div.
     */
    DragDropToMatch.prototype.getRoot = function() {
        return $(document.getElementById(this.containerId));
    };

    /**
     * Get drag home for a given choice.
     *.
     * @param {int} choice the choice number.
     * @returns {jQuery} containing that div.
     */
    DragDropToMatch.prototype.getDragHome = function(choice) {
        if (!this.getRoot().find('.draghome.dragplaceholder.choice' + choice).is(':visible')) {
            return this.getRoot().find('.draghomes li.draghome.infinite' +
                '.choice' + choice);
        }
        return this.getRoot().find('.draghome.dragplaceholder.choice' + choice);
    };

    /**
     * Get an unplaced choice .
     *
     * @param {int} choice the choice number.
     * @returns {jQuery} jQuery wrapping the unplaced choice. If there isn't one, the jQuery will be empty.
     */
    DragDropToMatch.prototype.getUnplacedChoice = function(choice) {
        return this.getRoot().find('.draghome.choice' + choice + '.unplaced').slice(0, 1);
    };

    /**
     * Get the drag that is currently in a given place.
     *
     * @param {int} place the place number.
     * @return {jQuery} the current drag (or an empty jQuery if none).
     */
    DragDropToMatch.prototype.getCurrentDragInPlace = function(place) {
        return this.getRoot().find('li.draghome.inplace' + place);
    };

    /**
     * Return the number of blanks in question set.
     *
     * @returns {int} the number of drops.
     */
    DragDropToMatch.prototype.noOfDropsIn = function() {
        return this.getRoot().find('.drop' ).length;
    };

    /**
     * Return the number at the end of the CSS class name with the given prefix.
     *
     * @param {jQuery} node
     * @param {String} prefix name prefix
     * @returns {Number|null} the suffix if found, else null.
     */
    DragDropToMatch.prototype.getClassnameNumericSuffix = function(node, prefix) {
        var classes = node.attr('class');
        if (classes !== '') {
            var classesArr = classes.split(' ');
            for (var index = 0; index < classesArr.length; index++) {
                var patt1 = new RegExp('^' + prefix + '([0-9])+$');
                if (patt1.test(classesArr[index])) {
                    var patt2 = new RegExp('([0-9])+$');
                    var match = patt2.exec(classesArr[index]);
                    return Number(match[0]);
                }
            }
        }
        return null;
    };

    /**
     * Get the choice number of a drag.
     *
     * @param {jQuery} drag the drag.
     * @returns {Number} the choice number.
     */
    DragDropToMatch.prototype.getChoice = function(drag) {
        return this.getClassnameNumericSuffix(drag, 'choice');
    };


    /**
     * Get the place number of a drop, or its corresponding hidden input.
     *
     * @param {jQuery} node the DOM node.
     * @returns {Number} the place number.
     */
    DragDropToMatch.prototype.getPlace = function(node) {
        return this.getClassnameNumericSuffix(node, 'place');
    };

    /**
     * Get drag clone for a given drag.
     *
     * @param {jQuery} drag the drag.
     * @returns {jQuery} the drag's clone.
     */
    DragDropToMatch.prototype.getDragClone = function(drag) {
        return this.getRoot().find('.draghomes li.draghome' +
            '.choice' + this.getChoice(drag) +
            '.dragplaceholder');
    };

    /**
     * Get infinite drag clones for given drag.
     *
     * @param {jQuery} drag the drag.
     * @param {Boolean} inHome in the home area or not.
     * @returns {jQuery} the drag's clones.
     */
    DragDropToMatch.prototype.getInfiniteDragClones = function(drag, inHome) {
        if (inHome) {
            return this.getRoot().find(
                '.draghomes li.draghome' +
                '.choice' + this.getChoice(drag) +
                '.infinite').not('.dragplaceholder');
        }
        return this.getRoot().find('.draghomes li.draghome' +
            '.choice' + this.getChoice(drag) +
            '.infinite').not('.dragplaceholder');
    };

    /**
     * Get drop for a given drag and place.
     *
     * @param {jQuery} drag the drag.
     * @param {Integer} currentPlace the current place of drag.
     * @returns {jQuery} the drop's clone.
     */
    DragDropToMatch.prototype.getDrop = function(drag, currentPlace) {
        return this.getRoot().find('.drop.place' + currentPlace);
    };

    /**
     * Check that the drag is drop to it's clone.
     *
     * @param {jQuery} drag The drag.
     * @param {jQuery} drop The drop.
     * @returns {boolean}
     */
    DragDropToMatch.prototype.isDragSameAsDrop = function(drag, drop) {
        return this.getChoice(drag) === this.getChoice(drop);
    };

    /**
     * Singleton that tracks all the DragDropToTextQuestions on this page, and deals
     * with event dispatching.
     *
     * @type {Object}
     */
    var questionManager = {
        /**
         * {boolean} used to ensure the event handlers are only initialised once per page.
         */
        eventHandlersInitialised: false,

        /**
         * {Object} ensures that the drag event handlers are only initialised once per question,
         * indexed by containerId (id on the .que div).
         */
        dragEventHandlersInitialised: {},

        /**
         * {boolean} is keyboard navigation or not.
         */
        isKeyboardNavigation: false,

        /**
         * {DragDropToMatch[]} all the questions on this page, indexed by containerId (id on the .que div).
         */
        questions: {},

        /**
         * Initialise questions.
         *
         * @param {String} containerId id of the outer div for this question.
         * @param {boolean} readOnly whether the question is being displayed read-only.
         */
        init: function(containerId, readOnly) {
            questionManager.questions[containerId] = new DragDropToMatch(containerId, readOnly);
            if (!questionManager.eventHandlersInitialised) {
                questionManager.setupEventHandlers();
                questionManager.eventHandlersInitialised = true;
            }
            if (!questionManager.dragEventHandlersInitialised.hasOwnProperty(containerId)) {
                questionManager.dragEventHandlersInitialised[containerId] = true;
                // We do not use the body event here to prevent the other event on Mobile device, such as scroll event.
                var questionContainer = document.getElementById(containerId);
                if (questionContainer.classList.contains('ddmatch') &&
                    !questionContainer.classList.contains('qtype_ddmatch-readonly')) {
                    // TODO: Convert all the jQuery selectors and events to native Javascript.
                    questionManager.addEventHandlersToDrag($(questionContainer).find('li.draghome'));
                }
            }
        },

        /**
         * Set up the event handlers that make this question type work. (Done once per page.)
         */
        setupEventHandlers: function() {
            $('body')
                .on('keydown',
                    '.que.ddmatch:not(.qtype_ddmatch-readonly) ul.drop',
                    questionManager.handleKeyPress)
                .on('keydown',
                    '.que.ddmatch:not(.qtype_ddmatch-readonly) li.draghome.placed:not(.beingdragged)',
                    questionManager.handleKeyPress)
                .on('qtype_ddmatch-dragmoved', questionManager.handleDragMoved);
        },

        /**
         * Binding the drag/touch event again for newly created element.
         *
         * @param {jQuery} element Element to bind the event
         */
        addEventHandlersToDrag: function(element) {
            // Unbind all the mousedown and touchstart events to prevent double binding.
            element.unbind('mousedown touchstart');
            element.on('mousedown touchstart', questionManager.handleDragStart);
        },

        /**
         * Handle mouse down / touch start on drags.
         * @param {Event} e the DOM event.
         */
        handleDragStart: function(e) {
            e.preventDefault();
            var question = questionManager.getQuestionForEvent(e);
            if (question) {
                question.handleDragStart(e);
            }
        },

        /**
         * Handle key down / press on drops.
         * @param {KeyboardEvent} e
         */
        handleKeyPress: function(e) {
            if (questionManager.isKeyboardNavigation) {
                return;
            }
            questionManager.isKeyboardNavigation = true;
            var question = questionManager.getQuestionForEvent(e);
            if (question) {
                question.handleKeyPress(e);
            }
        },

        /**
         * Given an event, work out which question it affects.
         *
         * @param {Event} e the event.
         * @returns {DragDropToMatch|undefined} The question, or undefined.
         */
        getQuestionForEvent: function(e) {
            var containerId = $(e.currentTarget).closest('.que.ddmatch').attr('id');
            return questionManager.questions[containerId];
        },

        /**
         * Handle when drag moved.
         *
         * @param {Event} e the event.
         * @param {jQuery} drag the drag
         * @param {jQuery} target the target
         * @param {DragDropToMatch} thisQ the question.
         */
        handleDragMoved: function(e, drag, target, thisQ) {
            drag.removeClass('beingdragged');
            drag.css('top', '').css('left', '');
            target.after(drag);
            target.removeClass('active');
            if (typeof drag.data('unplaced') !== 'undefined' && drag.data('unplaced') === true) {
                drag.removeClass('placed').addClass('unplaced');
                drag.removeAttr('tabindex');
                drag.removeData('unplaced');
                if (drag.hasClass('infinite') && thisQ.getInfiniteDragClones(drag, true).length > 1) {
                    thisQ.getInfiniteDragClones(drag, true).first().remove();
                }
            }
            if (questionManager.isKeyboardNavigation) {
                questionManager.isKeyboardNavigation = false;
            }
            if (thisQ.isQuestionInteracted()) {
                // Save the new answered value.
                thisQ.questionAnswer = thisQ.getQuestionAnsweredValues();
            }
        },

        
    };

    /**
     * @alias module:qtype_ddmatch/ddmatch
     */
    return {
        /**
         * Initialise one drag-drop into text question.
         *
         * @param {String} containerId id of the outer div for this question.
         * @param {boolean} readOnly whether the question is being displayed read-only.
         */
        init: questionManager.init
    };
});
