<script>
/**
 * AudioManager - Procedural audio for satisfying feedback
 */
class AudioManager {
    constructor() {
        this.audioContext = null;
        this.masterGain = null;
        this.volume = 0.7;
        this.isMuted = false;
        this.soundtrack = null;
        this.soundtrackPlaying = false;
        this.radioTracks = [];
        this.radioQueue = [];
        this.lastTrack = null;
        this.radioAudio = null;
        
        // Engine sound system
        this.engineRunning = false;
        this.engineOscillators = [];
        this.engineGains = [];
        this.engineNoiseSource = null;
        this.engineNoiseGain = null;
        this.currentRPM = 0;
        this.targetRPM = 0;
        this.currentSpeedRatio = 0;
        
        // Horn sound system
        this.hornPlaying = false;
        this.hornOscillators = [];
        this.hornGains = [];
        this.hornStopTimeout = null;
        
        // Ambient audio
        this.ambientAudio = null;
        this.ambientPlaying = false;
    }
    
    init() {
        // Initialize audio context on first user interaction
        const initAudio = () => {
            this.ensureAudioContext();
            document.removeEventListener('click', initAudio);
            document.removeEventListener('keydown', initAudio);
            document.removeEventListener('touchstart', initAudio);
        };
        document.addEventListener('click', initAudio);
        document.addEventListener('keydown', initAudio);
        document.addEventListener('touchstart', initAudio);
        
        // Radio playlist (randomized, no repeats until all played)
        this.loadRadioTracks();
        
        // Start ambient audio
        this.startAmbient();
    }
    
    startAmbient() {
        if (this.ambientPlaying) return;
        
        this.ambientAudio = new Audio('assets/audio/outside.mp3');
        this.ambientAudio.loop = true;
        this.ambientAudio.volume = this.isMuted ? 0 : 0.25; // Subtle background volume
        
        // Play on user interaction (browsers require this)
        const playAmbient = () => {
            if (this.ambientAudio && !this.ambientPlaying) {
                this.ambientAudio.play().then(() => {
                    this.ambientPlaying = true;
                }).catch(() => {});
            }
        };
        
        document.addEventListener('click', playAmbient, { once: true });
        document.addEventListener('keydown', playAmbient, { once: true });
        document.addEventListener('touchstart', playAmbient, { once: true });
    }
    
    stopAmbient() {
        if (this.ambientAudio) {
            this.ambientAudio.pause();
            this.ambientAudio.currentTime = 0;
            this.ambientPlaying = false;
        }
    }
    
    setAmbientVolume(vol) {
        if (this.ambientAudio) {
            this.ambientAudio.volume = vol;
        }
    }
    
    ensureAudioContext() {
        if (!this.audioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.masterGain = this.audioContext.createGain();
            this.masterGain.gain.value = this.volume;
            this.masterGain.connect(this.audioContext.destination);
        }
        if (this.audioContext.state === 'suspended') {
            this.audioContext.resume();
        }
        return this.audioContext;
    }
    
    async loadRadioTracks() {
        try {
            const response = await fetch('assets/radio/list.php');
            const data = await response.json();
            if (Array.isArray(data) && data.length > 0) {
                this.radioTracks = data;
                this.resetRadioQueue();
            } else {
                // No tracks found
            }
        } catch (e) {
            
        }
    }
    
    toggleSoundtrack() {
        if (this.soundtrackPlaying) {
            this.stopSoundtrack();
            return false;
        } else {
            this.soundtrackPlaying = true; // Set immediately for UI update
            this.playSoundtrack();
            return true;
        }
    }
    
    playSoundtrack() {
        if (!this.radioTracks.length) return;
        if (!this.radioAudio) {
            this.radioAudio = new Audio();
            this.radioAudio.volume = 0.3;
            this.radioAudio.addEventListener('ended', () => {
                if (this.soundtrackPlaying) this.playNextRadioTrack();
            });
        }
        this.playNextRadioTrack();
    }
    
    stopSoundtrack() {
        if (this.radioAudio) {
            this.radioAudio.pause();
            this.radioAudio.currentTime = 0;
        }
        this.soundtrackPlaying = false;
    }

    resetRadioQueue() {
        this.radioQueue = this.shuffleArray([...this.radioTracks]);
        // Prevent immediate repeat across cycles
        if (this.lastTrack && this.radioQueue.length > 1 && this.radioQueue[0] === this.lastTrack) {
            const swapIndex = 1 + Math.floor(Math.random() * (this.radioQueue.length - 1));
            const temp = this.radioQueue[0];
            this.radioQueue[0] = this.radioQueue[swapIndex];
            this.radioQueue[swapIndex] = temp;
        }
    }

    playNextRadioTrack() {
        if (!this.radioQueue.length) {
            this.resetRadioQueue();
        }
        const nextTrack = this.radioQueue.shift();
        if (!nextTrack) return;
        this.lastTrack = nextTrack;
        const separator = nextTrack.includes('?') ? '&' : '?';
        this.radioAudio.src = `${nextTrack}${separator}v1`;
        this.radioAudio.play().catch(() => {});
        this.soundtrackPlaying = true;
    }

    shuffleArray(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }
    
    playSound(type) {
        if (this.isMuted) return;
        
        const ctx = this.ensureAudioContext();
        if (!ctx) return;
        
        const now = ctx.currentTime;
        
        if (type === 'place') {
            // Satisfying thunk
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(150, now);
            osc.frequency.exponentialRampToValueAtTime(60, now + 0.1);
            gain.gain.setValueAtTime(0.3, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
            osc.connect(gain);
            gain.connect(this.masterGain);
            osc.start(now);
            osc.stop(now + 0.15);
        } else if (type === 'pickup') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(200, now);
            osc.frequency.exponentialRampToValueAtTime(400, now + 0.08);
            gain.gain.setValueAtTime(0.15, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
            osc.connect(gain);
            gain.connect(this.masterGain);
            osc.start(now);
            osc.stop(now + 0.1);
        } else if (type === 'complete') {
            const notes = [523.25, 659.25, 783.99, 1046.50];
            notes.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + i * 0.1);
                gain.gain.setValueAtTime(0, now + i * 0.1);
                gain.gain.linearRampToValueAtTime(0.2, now + i * 0.1 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.01, now + i * 0.1 + 0.4);
                osc.connect(gain);
                gain.connect(this.masterGain);
                osc.start(now + i * 0.1);
                osc.stop(now + i * 0.1 + 0.5);
            });
        } else if (type === 'error') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(200, now);
            osc.frequency.linearRampToValueAtTime(150, now + 0.15);
            gain.gain.setValueAtTime(0.1, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
            osc.connect(gain);
            gain.connect(this.masterGain);
            osc.start(now);
            osc.stop(now + 0.2);
        } else if (type === 'gearshift') {
            // Subtle mechanical click for gear shift
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            const filter = ctx.createBiquadFilter();
            
            // White noise burst for mechanical click
            osc.type = 'square';
            osc.frequency.setValueAtTime(80, now);
            osc.frequency.exponentialRampToValueAtTime(40, now + 0.04);
            
            filter.type = 'lowpass';
            filter.frequency.value = 400;
            
            gain.gain.setValueAtTime(0.08, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.06);
            
            osc.connect(filter);
            filter.connect(gain);
            gain.connect(this.masterGain);
            osc.start(now);
            osc.stop(now + 0.06);
        }
    }
    
    pauseAll() { if (this.audioContext) this.audioContext.suspend(); }
    resumeAll() { if (this.audioContext) this.audioContext.resume(); }
    
    // Horn sound (plays while held)
    startHorn() {
        if (this.hornPlaying) return;
        
        const ctx = this.ensureAudioContext();
        if (!ctx) return;
        
        // Cancel any pending stop timeout
        if (this.hornStopTimeout) {
            clearTimeout(this.hornStopTimeout);
            this.hornStopTimeout = null;
        }
        
        // Stop any lingering oscillators from previous horn
        this.hornOscillators.forEach(osc => {
            try { osc.stop(); } catch(e) {}
        });
        
        this.hornPlaying = true;
        this.hornOscillators = [];
        this.hornGains = [];
        
        const now = ctx.currentTime;
        
        // Two-tone truck horn (like a semi truck)
        const frequencies = [277, 349]; // C#4 and F4 - classic truck horn interval
        
        frequencies.forEach((freq) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.type = 'sawtooth';
            osc.frequency.value = freq;
            
            // Add slight vibrato for realism
            const vibrato = ctx.createOscillator();
            const vibratoGain = ctx.createGain();
            vibrato.frequency.value = 5;
            vibratoGain.gain.value = 3;
            vibrato.connect(vibratoGain);
            vibratoGain.connect(osc.frequency);
            
            // Quick fade in
            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.15, now + 0.05);
            
            // Low pass filter for warmer sound
            const filter = ctx.createBiquadFilter();
            filter.type = 'lowpass';
            filter.frequency.value = 1500;
            
            osc.connect(filter);
            filter.connect(gain);
            gain.connect(this.masterGain);
            
            osc.start(now);
            vibrato.start(now);
            
            this.hornOscillators.push(osc, vibrato);
            this.hornGains.push(gain);
        });
    }
    
    stopHorn() {
        if (!this.hornPlaying) return;
        
        const ctx = this.audioContext;
        if (!ctx) return;
        
        this.hornPlaying = false;
        
        const now = ctx.currentTime;
        const oscsToStop = [...this.hornOscillators];
        
        // Quick fade out
        this.hornGains.forEach(gain => {
            gain.gain.cancelScheduledValues(now);
            gain.gain.setValueAtTime(gain.gain.value, now);
            gain.gain.linearRampToValueAtTime(0, now + 0.08);
        });
        
        // Stop oscillators after fade (store timeout so it can be cancelled)
        this.hornStopTimeout = setTimeout(() => {
            oscsToStop.forEach(osc => {
                try { osc.stop(); } catch(e) {}
            });
            this.hornStopTimeout = null;
        }, 100);
        
        // Clear arrays immediately so new horn can start fresh
        this.hornOscillators = [];
        this.hornGains = [];
    }
    
    // ===== ENGINE SOUND SYSTEM =====
    
    startEngine() {
        if (this.engineRunning) return;
        
        // Ensure audio context exists
        const ctx = this.ensureAudioContext();
        if (!ctx) {
            console.warn('Could not start engine - no audio context');
            return;
        }
        
        this.engineRunning = true;
        this.currentRPM = 800; // Idle RPM
        this.targetRPM = 800;
        
        // Create engine oscillators (layered for deep, dark truck sound)
        // Main growl - triangle for smoother, darker tone
        const osc1 = ctx.createOscillator();
        osc1.type = 'triangle';
        const gain1 = ctx.createGain();
        gain1.gain.value = 0.08;
        osc1.connect(gain1);
        
        // Low mid rumble
        const osc2 = ctx.createOscillator();
        osc2.type = 'sine';
        const gain2 = ctx.createGain();
        gain2.gain.value = 0.12;
        osc2.connect(gain2);
        
        // Deep sub-bass rumble (the "chest thump")
        const osc3 = ctx.createOscillator();
        osc3.type = 'sine';
        const gain3 = ctx.createGain();
        gain3.gain.value = 0.30;
        osc3.connect(gain3);
        
        // Extra low sub-bass for that diesel feel
        const osc4 = ctx.createOscillator();
        osc4.type = 'sine';
        const gain4 = ctx.createGain();
        gain4.gain.value = 0.25;
        osc4.connect(gain4);
        
        // Create a low-pass filter - very dark
        const filter = ctx.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.value = 300; // Very low cutoff for dark sound
        filter.Q.value = 1.5;
        this.engineFilter = filter;
        
        // Connect all through filter
        gain1.connect(filter);
        gain2.connect(filter);
        gain3.connect(filter);
        gain4.connect(filter);
        filter.connect(this.masterGain);
        
        // Store references
        this.engineOscillators = [osc1, osc2, osc3, osc4];
        this.engineGains = [gain1, gain2, gain3, gain4];
        
        // Set initial frequencies
        this.updateEngineFrequencies();
        
        // Start oscillators
        osc1.start();
        osc2.start();
        osc3.start();
        osc4.start();
        
        // Add noise for exhaust texture
        this.createEngineNoise();
    }
    
    createEngineNoise() {
        if (!this.audioContext) return;
        
        const ctx = this.audioContext;
        const bufferSize = ctx.sampleRate * 2;
        const noiseBuffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        const output = noiseBuffer.getChannelData(0);
        
        // Very deep brown noise (maximum bass for dark diesel rumble)
        let lastOut = 0;
        for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            output[i] = (lastOut + (0.01 * white)) / 1.01; // Even slower = even deeper
            lastOut = output[i];
            output[i] *= 6; // Boost
        }
        
        this.engineNoiseSource = ctx.createBufferSource();
        this.engineNoiseSource.buffer = noiseBuffer;
        this.engineNoiseSource.loop = true;
        
        this.engineNoiseGain = ctx.createGain();
        this.engineNoiseGain.gain.value = 0.05;
        
        // Filter noise - very low for dark exhaust sound
        const noiseFilter = ctx.createBiquadFilter();
        noiseFilter.type = 'lowpass';
        noiseFilter.frequency.value = 150; // Very dark
        noiseFilter.Q.value = 1;
        this.engineNoiseFilter = noiseFilter;
        
        this.engineNoiseSource.connect(noiseFilter);
        noiseFilter.connect(this.engineNoiseGain);
        this.engineNoiseGain.connect(this.masterGain);
        
        this.engineNoiseSource.start();
    }
    
    stopEngine() {
        if (!this.engineRunning) return;
        
        this.engineRunning = false;
        
        // Stop oscillators
        this.engineOscillators.forEach(osc => {
            try { osc.stop(); } catch(e) {}
        });
        this.engineOscillators = [];
        this.engineGains = [];
        
        // Stop noise
        if (this.engineNoiseSource) {
            try { this.engineNoiseSource.stop(); } catch(e) {}
            this.engineNoiseSource = null;
        }
    }
    
    updateEngineFrequencies() {
        if (!this.engineRunning || this.engineOscillators.length === 0) return;
        
        // Convert RPM to base frequency (big diesel - very low firing rate)
        const baseFreq = (this.currentRPM / 60) * 1.2; // Even lower for darker sound
        
        // Update oscillator frequencies - all shifted lower
        const [osc1, osc2, osc3, osc4] = this.engineOscillators;
        if (osc1) osc1.frequency.value = baseFreq * 0.8;     // Main growl (lower)
        if (osc2) osc2.frequency.value = baseFreq * 0.6;     // Low mid
        if (osc3) osc3.frequency.value = baseFreq / 2;       // Sub bass
        if (osc4) osc4.frequency.value = baseFreq / 5;       // Deep sub (really low)
        
        // Adjust filter based on RPM (stays dark even at high RPM)
        if (this.engineFilter) {
            this.engineFilter.frequency.value = 200 + (this.currentRPM / 6000) * 300; // Very dark
        }
        
        // Speed factor - quieter when idle/stationary, louder when moving
        const speedFactor = this.currentSpeedRatio || 0; // 0 when stopped, 1 at max speed
        const volumeMultiplier = 0.3 + speedFactor * 0.7; // 30% volume at idle, 100% at full speed
        
        // Adjust gain based on RPM AND speed - more bass heavy
        const rpmFactor = (this.currentRPM - 800) / 5200; // 0 at idle, 1 at max
        if (this.engineGains[0]) this.engineGains[0].gain.value = (0.06 + rpmFactor * 0.08) * volumeMultiplier; // Less highs
        if (this.engineGains[1]) this.engineGains[1].gain.value = (0.10 + rpmFactor * 0.10) * volumeMultiplier; // Low mid
        if (this.engineGains[2]) this.engineGains[2].gain.value = (0.25 + rpmFactor * 0.18) * volumeMultiplier; // More sub
        if (this.engineGains[3]) this.engineGains[3].gain.value = (0.20 + rpmFactor * 0.15) * volumeMultiplier; // More deep sub
        
        // Noise also quieter when stopped - keep very low
        if (this.engineNoiseGain) {
            this.engineNoiseGain.gain.value = (0.04 + rpmFactor * 0.06) * volumeMultiplier;
        }
        if (this.engineNoiseFilter) {
            this.engineNoiseFilter.frequency.value = 150 + rpmFactor * 150; // Very dark exhaust
        }
    }
    
    // Call this every frame with the truck's current speed and max speed
    updateEngineSound(speed, maxSpeed, isAccelerating, isBraking) {
        if (!this.engineRunning) return;
        
        // Map speed to RPM (800 idle, up to 6000 at max speed)
        const speedRatio = Math.abs(speed) / maxSpeed;
        this.currentSpeedRatio = speedRatio; // Store for volume calculation
        
        // Base RPM from speed
        let targetRPM = 800 + speedRatio * 4500;
        
        // Boost RPM when accelerating (revving)
        if (isAccelerating) {
            targetRPM += 700;
        }
        
        // Drop RPM slightly when braking (engine braking sound)
        if (isBraking && speed > 0) {
            targetRPM = Math.max(800, targetRPM - 300);
        }
        
        // Clamp RPM
        targetRPM = Math.max(800, Math.min(6000, targetRPM));
        this.targetRPM = targetRPM;
        
        // Smoothly interpolate current RPM toward target
        const rpmLerpSpeed = isAccelerating ? 0.08 : 0.05; // Revs up faster than down
        this.currentRPM += (this.targetRPM - this.currentRPM) * rpmLerpSpeed;
        
        // Update oscillator frequencies
        this.updateEngineFrequencies();
    }
}
</script>

