import fs from 'node:fs';
import path from 'node:path';
import { log } from './common.js';

// Encoders can finish writing while a stream closes. A temporary ENOTEMPTY,
// EBUSY or permission error must not terminate the entire video receiver.
// Retry asynchronously; if still busy, leave the orphan for startup cleanup.
export async function removeMediaDirectory(root, id, { remove = fs.promises.rm, report = log } = {}) {
    const parent = path.resolve(root), target = path.resolve(parent, id);
    if (!/^[a-f0-9-]{36}$/.test(id) || path.dirname(target) !== parent) return false;
    try {
        await remove(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 100 });
        return true;
    } catch (error) {
        report('video_cleanup_deferred', { reason: error.code || 'cleanup_failed' });
        return false;
    }
}
