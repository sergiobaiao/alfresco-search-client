'use strict';

/**
 * Determine whether a camera entry should be considered failing.
 * @param {object} camera - Camera status payload.
 * @param {number|undefined} offlineThresholdSeconds - Optional threshold that
 *   defines how stale the last frame timestamp can be before being treated as
 *   failing. If undefined, the timestamp is ignored.
 * @param {number} now - Current timestamp in milliseconds.
 * @returns {boolean}
 */
function isCameraFailing(camera, offlineThresholdSeconds, now) {
    if (!camera || typeof camera !== 'object') {
        return true;
    }

    const normalized = (value) => (typeof value === 'string' ? value.toLowerCase() : value);

    const state = normalized(camera.state || camera.status || camera.health_state);
    if (typeof state === 'string') {
        if (['failed', 'failing', 'offline', 'disconnected', 'error'].includes(state)) {
            return true;
        }
    }

    if (typeof camera.healthy === 'boolean') {
        return camera.healthy === false;
    }

    const health = normalized(camera.health || camera.healthStatus);
    if (typeof health === 'string' && !['ok', 'good', 'healthy'].includes(health)) {
        return true;
    }

    if (typeof camera.online === 'boolean' && camera.online === false) {
        return true;
    }

    if (typeof camera.connected === 'boolean' && camera.connected === false) {
        return true;
    }

    if (offlineThresholdSeconds !== undefined) {
        const lastFrameTimestamp = extractLastFrameTimestamp(camera);
        if (lastFrameTimestamp !== undefined && lastFrameTimestamp !== null) {
            const thresholdMillis = offlineThresholdSeconds * 1000;
            if (!Number.isFinite(lastFrameTimestamp)) {
                return true;
            }
            if (now - lastFrameTimestamp > thresholdMillis) {
                return true;
            }
        }
    }

    return false;
}

function extractLastFrameTimestamp(camera) {
    const keys = [
        'lastFrameTime',
        'last_frame_time',
        'lastFrame',
        'last_frame',
        'lastImageReceived',
        'last_image_received'
    ];

    for (const key of keys) {
        if (key in camera) {
            const value = camera[key];
            if (typeof value === 'number') {
                if (value > 1e12) {
                    return value; // assume already in ms
                }
                if (value > 0) {
                    return value * 1000; // assume seconds
                }
                return NaN;
            }
            if (typeof value === 'string' && value.trim() !== '') {
                const parsed = Date.parse(value);
                if (Number.isNaN(parsed)) {
                    return NaN;
                }
                return parsed;
            }
        }
    }

    return undefined;
}

/**
 * Return the subset of cameras that are currently failing.
 *
 * @param {Array<object>} cameras
 * @param {object} [options]
 * @param {number} [options.offlineThresholdSeconds]
 * @param {Date|number} [options.now]
 * @returns {Array<object>}
 */
function getFailingCameras(cameras, options = {}) {
    const { offlineThresholdSeconds, now = Date.now() } = options;
    if (!Array.isArray(cameras) || cameras.length === 0) {
        return [];
    }

    return cameras.filter((camera) => isCameraFailing(camera, offlineThresholdSeconds, typeof now === 'number' ? now : now.valueOf()));
}

/**
 * Determine whether any cameras are failing.
 *
 * @param {Array<object>} cameras
 * @param {object} [options]
 * @returns {boolean}
 */
function hasFailingCameras(cameras, options = {}) {
    return getFailingCameras(cameras, options).length > 0;
}

/**
 * Generate a human friendly summary about the fleet health.
 *
 * @param {Array<object>} cameras
 * @param {object} [options]
 * @returns {{ failing: Array<object>, message: string }}
 */
function summarizeCameraHealth(cameras, options = {}) {
    const failing = getFailingCameras(cameras, options);
    if (failing.length === 0) {
        return { failing, message: 'No failing cameras detected.' };
    }

    const failingNames = failing
        .map((camera, index) => {
            if (!camera) {
                return `Camera ${index + 1}`;
            }
            return camera.name || camera.id || camera.slug || `Camera ${index + 1}`;
        })
        .join(', ');

    return {
        failing,
        message: `${failing.length} failing cameras detected: ${failingNames}`
    };
}

module.exports = {
    extractLastFrameTimestamp,
    getFailingCameras,
    hasFailingCameras,
    summarizeCameraHealth,
    _private: {
        isCameraFailing
    }
};
