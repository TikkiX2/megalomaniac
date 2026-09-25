import {
    closestCorners,
    pointerWithin,
    rectIntersection,
    type CollisionDetection,
} from '@dnd-kit/core';

/**
 * Board-friendly collision detection: the pointer decides the target column,
 * preferring a task container over its parent column so the drop index can be
 * computed. Falls back to rect intersection and closest corners when the
 * pointer is outside every droppable (e.g. during keyboard dragging).
 */
export function boardCollisionDetection(columnKeys: string[]): CollisionDetection {
    return (args) => {
        const pointerCollisions = pointerWithin(args);

        if (pointerCollisions.length > 0) {
            const columnSet = new Set(columnKeys);
            const overTask = pointerCollisions.find((collision) => !columnSet.has(String(collision.id)));

            return overTask ? [overTask] : pointerCollisions;
        }

        const rectCollisions = rectIntersection(args);

        if (rectCollisions.length > 0) {
            return rectCollisions;
        }

        return closestCorners(args);
    };
}
