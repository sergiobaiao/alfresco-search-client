'use strict';

const assert = require('assert');
const {
    extractLastFrameTimestamp,
    getFailingCameras,
    hasFailingCameras,
    summarizeCameraHealth,
    _private
} = require('../cameraStatus');

(function testOfflineStateCountsAsFailure() {
    const cameras = [
        { name: 'Cam A', state: 'online' },
        { name: 'Cam B', state: 'offline' },
        { name: 'Cam C', state: 'failed' }
    ];
    const failing = getFailingCameras(cameras);
    assert.strictEqual(failing.length, 2, 'Offline and failed cameras should be reported');
    assert.deepStrictEqual(failing.map((c) => c.name), ['Cam B', 'Cam C']);
})();

(function testBooleanFlagsInfluenceResult() {
    const cameras = [
        { name: 'Cam D', online: false },
        { name: 'Cam E', connected: false },
        { name: 'Cam F', healthy: true }
    ];
    const failing = getFailingCameras(cameras);
    assert.strictEqual(failing.length, 2, 'online=false and connected=false should count as failing');
    assert.deepStrictEqual(failing.map((c) => c.name), ['Cam D', 'Cam E']);
})();

(function testOfflineThresholdWithSeconds() {
    const now = Date.parse('2023-01-01T00:10:00Z');
    const tenMinutesAgo = now - 10 * 60 * 1000;
    const cameras = [
        { name: 'Cam G', last_frame_time: tenMinutesAgo / 1000 }, // 10 minutes ago when treated as seconds
        { name: 'Cam H', lastFrameTime: now - 30 * 1000 } // 30 seconds ago
    ];
    const failing = getFailingCameras(cameras, { offlineThresholdSeconds: 60, now });
    assert.strictEqual(failing.length, 1, 'Only stale timestamps should be failing');
    assert.strictEqual(failing[0].name, 'Cam G');
})();

(function testInvalidCameraObjectsAreFailing() {
    const failing = getFailingCameras([null, undefined, 'not-an-object']);
    assert.strictEqual(failing.length, 3);
})();

(function testSummaryMessageFormatting() {
    const cameras = [
        { name: 'Cam I', state: 'offline' },
        { name: 'Cam J', state: 'online' }
    ];
    const summary = summarizeCameraHealth(cameras);
    assert.strictEqual(summary.failing.length, 1);
    assert.ok(summary.message.includes('Cam I'));
    assert.strictEqual(hasFailingCameras(cameras), true);
})();

(function testExtractLastFrameTimestampParsing() {
    const base = Date.now();
    const iso = new Date(base).toISOString();
    assert.strictEqual(extractLastFrameTimestamp({ lastFrameTime: base }), base);
    assert.strictEqual(extractLastFrameTimestamp({ last_frame_time: Math.floor(base / 1000) }), Math.floor(base / 1000) * 1000);
    assert.strictEqual(extractLastFrameTimestamp({ last_frame: iso }), Date.parse(iso));
    assert.ok(Number.isNaN(extractLastFrameTimestamp({ last_frame: 'invalid date' })), 'Invalid dates should return NaN');
})();

(function testPrivateIsCameraFailingExposure() {
    const now = Date.parse('2023-01-01T00:00:00Z');
    const failing = _private.isCameraFailing({ lastFrame: '2022-12-31T23:00:00Z' }, 600, now);
    assert.strictEqual(failing, true, 'Private helper should respect thresholds');
})();

console.log('All camera status tests passed');
