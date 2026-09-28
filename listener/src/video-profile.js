import path from 'node:path';

export const videoStartupBuffer = (device, profile = process.env.VIDEO_HLS_PROFILE || 'fast') =>
    profile === 'fast' && ['JK114', 'ES500-603'].includes(device.model) ? 4 : 8;

// Independent one-second segments let browsers begin before a camera's long
// GOP finishes. Keep the source dimensions and use a bounded encoder thread.
export function videoMuxerArgs(device, directory, {profile = process.env.VIDEO_HLS_PROFILE || 'fast'} = {}) {
    const rate = Number(device.frame_rate);
    if (!Number.isFinite(rate) || rate < 1 || rate > 60) throw new Error('Invalid video frame rate');
    const fast = videoStartupBuffer(device, profile) === 4;
    const cadence = Math.max(1, Math.round(rate));
    return [
        '-hide_banner', '-loglevel', 'warning', '-nostdin', '-fflags', '+genpts',
        '-probesize', fast ? '8192' : '32768', '-analyzeduration', fast ? '100000' : '1000000',
        ...(fast ? ['-fpsprobesize','0','-threads','1'] : []),
        '-r', String(rate), '-f', 'h264', '-i', 'pipe:0', '-map', '0:v:0', '-an',
        ...(fast ? [
            '-c:v','libx264','-preset','veryfast','-tune','zerolatency','-crf','20',
            '-pix_fmt','yuv420p','-threads','1','-bf','0','-g',String(cadence),
            '-keyint_min',String(cadence),'-sc_threshold','0','-r',String(rate),
        ] : ['-c:v','copy']),
        // Preserve the established opt-in timing correction for raw ES500 video.
        ...(device.normalize_video_timestamps ? ['-bsf:v',`setts=ts=N/(${rate}*TB)`] : []),
        '-f','hls','-hls_time',fast ? '1' : '2','-hls_list_size',fast ? '40' : '20',
        '-hls_flags','delete_segments+temp_file+omit_endlist'+(fast ? '+independent_segments' : ''),
        '-hls_delete_threshold',fast ? '10' : '5',
        '-hls_segment_filename',path.join(directory,'segment-%06d.ts'),path.join(directory,'index.m3u8'),
    ];
}
