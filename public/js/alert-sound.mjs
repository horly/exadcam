// A descending two-note message chime, distinct from Tracking's ascending chime.
export function playAlertTone(context) {
    if (!context || context.state !== 'running') return false;
    const output = context.createGain();
    output.gain.value = 0.7;
    output.connect(context.destination);
    const notes = [{hz:1318.51,offset:0,duration:0.19}, {hz:987.77,offset:0.145,duration:0.36}];
    let remaining = notes.length * 3;
    for (const note of notes) {
        for (const [ratio, level, decay] of [[1,0.4,1],[2,0.1,0.6],[3.01,0.035,0.35]]) {
            const oscillator = context.createOscillator(), gain = context.createGain();
            const start = context.currentTime + 0.005 + note.offset, end = start + note.duration * decay;
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(note.hz * ratio,start);
            gain.gain.setValueAtTime(0.0001,start);
            gain.gain.exponentialRampToValueAtTime(level,start+0.004);
            gain.gain.exponentialRampToValueAtTime(0.0001,end);
            oscillator.connect(gain); gain.connect(output);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); if (--remaining === 0) output.disconnect(); };
            oscillator.start(start); oscillator.stop(end+0.01);
        }
    }
    return true;
}
