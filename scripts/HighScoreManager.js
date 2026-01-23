/**
 * HighScoreManager - Handles multiple player profiles and leaderboard API
 */
class HighScoreManager {
    constructor() {
<<<<<<<< HEAD:scripts/HighScoreManager.js
        this.apiUrl = 'https://www.junkdoctorsnj.com/game/api/highscores.php';
========
        this.apiUrl = 'api/highscores.php';
>>>>>>>> origin/main:scripts/HighScoreManager.php
        this.storageKey = 'opl_profiles';
        this.activeProfileKey = 'opl_active_profile';
        
        // Load profiles from localStorage
        this.profiles = this.loadProfiles();
        this.activePlayerId = this.loadActivePlayerId();
        
        // Migrate from old cookie system if needed
        this.migrateFromCookies();
        
        this.personalBest = 0;
        this.currentLevel = 1;
        this.isEditing = false;
        
        this.elements = {
            playerSection: document.getElementById('player-section'),
            playerSelect: document.getElementById('player-select'),
            btnEditPlayer: document.getElementById('btn-edit-player'),
            btnDeletePlayer: document.getElementById('btn-delete-player'),
            newPlayerRow: document.getElementById('new-player-row'),
            usernameInput: document.getElementById('username-input'),
            btnSavePlayer: document.getElementById('btn-save-player'),
            usernameHint: document.getElementById('username-hint'),
            btnLeaderboard: document.getElementById('btn-leaderboard'),
            leaderboardModal: document.getElementById('leaderboard-modal'),
            leaderboardList: document.getElementById('leaderboard-list'),
            leaderboardTotal: document.getElementById('leaderboard-total'),
            btnCloseLeaderboard: document.getElementById('btn-close-leaderboard'),
            gameoverModal: document.getElementById('gameover-modal'),
            gameoverLevel: document.getElementById('gameover-level'),
            gameoverMessage: document.getElementById('gameover-message'),
            btnPlayAgain: document.getElementById('btn-play-again'),
            btnViewLeaderboard: document.getElementById('btn-view-leaderboard'),
            menuLeaderboard: document.getElementById('menu-leaderboard'),
            levelSelectSection: document.getElementById('level-select-section'),
            levelSelect: document.getElementById('level-select')
        };
        
        this.setupEventListeners();
        this.populatePlayerSelect();
        this.loadActiveProfile();
    }
    
    // Profile management
    loadProfiles() {
        try {
            const data = localStorage.getItem(this.storageKey);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            return [];
        }
    }
    
    saveProfiles() {
        localStorage.setItem(this.storageKey, JSON.stringify(this.profiles));
    }
    
    loadActivePlayerId() {
        return localStorage.getItem(this.activeProfileKey) || null;
    }
    
    saveActivePlayerId(playerId) {
        if (playerId) {
            localStorage.setItem(this.activeProfileKey, playerId);
        } else {
            localStorage.removeItem(this.activeProfileKey);
        }
        this.activePlayerId = playerId;
    }
    
    generatePlayerId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    getActiveProfile() {
        if (!this.activePlayerId) return null;
        return this.profiles.find(p => p.playerId === this.activePlayerId) || null;
    }
    
    addProfile(username) {
        const playerId = this.generatePlayerId();
        const profile = {
            playerId,
            username,
            personalBest: 1,
            createdAt: Date.now()
        };
        this.profiles.push(profile);
        this.saveProfiles();
        this.saveActivePlayerId(playerId);
        return profile;
    }
    
    updateProfile(playerId, updates) {
        const profile = this.profiles.find(p => p.playerId === playerId);
        if (profile) {
            Object.assign(profile, updates);
            this.saveProfiles();
        }
    }
    
    deleteProfile(playerId) {
        this.profiles = this.profiles.filter(p => p.playerId !== playerId);
        this.saveProfiles();
        
        // If deleted the active profile, switch to another or none
        if (this.activePlayerId === playerId) {
            const newActive = this.profiles.length > 0 ? this.profiles[0].playerId : null;
            this.saveActivePlayerId(newActive);
        }
    }
    
    // Migrate from old cookie-based system
    migrateFromCookies() {
        const cookies = document.cookie.split(';');
        let oldPlayerId = null;
        let oldUsername = null;
        
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'opl_player_id') oldPlayerId = decodeURIComponent(value);
            if (name === 'opl_username') oldUsername = decodeURIComponent(value);
        }
        
        // If we have old data and no profiles yet, migrate
        if (oldPlayerId && this.profiles.length === 0) {
            const profile = {
                playerId: oldPlayerId,
                username: oldUsername || 'Player 1',
                personalBest: 1,
                createdAt: Date.now()
            };
            this.profiles.push(profile);
            this.saveProfiles();
            this.saveActivePlayerId(oldPlayerId);
            
            // Clear old cookies
            document.cookie = 'opl_player_id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = 'opl_username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        }
    }
    
    // UI methods
    populatePlayerSelect() {
        if (!this.elements.playerSelect) return;
        
        this.elements.playerSelect.innerHTML = '';
        
        // Add existing profiles
        this.profiles.forEach(profile => {
            const option = document.createElement('option');
            option.value = profile.playerId;
            option.textContent = profile.username;
            this.elements.playerSelect.appendChild(option);
        });
        
        // Add "New Player" option
        const newOption = document.createElement('option');
        newOption.value = 'new';
        newOption.textContent = '+ New Player';
        this.elements.playerSelect.appendChild(newOption);
        
        // Select active profile or show new player form
        if (this.activePlayerId && this.profiles.find(p => p.playerId === this.activePlayerId)) {
            this.elements.playerSelect.value = this.activePlayerId;
            this.showPlayerButtons(true);
        } else if (this.profiles.length > 0) {
            this.elements.playerSelect.value = this.profiles[0].playerId;
            this.saveActivePlayerId(this.profiles[0].playerId);
            this.showPlayerButtons(true);
        } else {
            this.elements.playerSelect.value = 'new';
            this.showNewPlayerForm(true);
        }
    }
    
    showPlayerButtons(show) {
        if (this.elements.btnEditPlayer) {
            this.elements.btnEditPlayer.style.display = show ? 'block' : 'none';
        }
        if (this.elements.btnDeletePlayer) {
            this.elements.btnDeletePlayer.style.display = show ? 'block' : 'none';
        }
    }
    
    showNewPlayerForm(show) {
        if (this.elements.newPlayerRow) {
            this.elements.newPlayerRow.classList.toggle('hidden', !show);
        }
        this.showPlayerButtons(!show);
        
        if (show && this.elements.usernameInput) {
            this.elements.usernameInput.value = '';
            this.elements.usernameInput.placeholder = 'Enter your name';
            setTimeout(() => this.elements.usernameInput.focus(), 100);
        }
    }
    
    async loadActiveProfile() {
        const profile = this.getActiveProfile();
        
        if (profile) {
            // Check server for latest personal best
            await this.checkExistingScore();
        } else {
            this.personalBest = 1;
            this.updateLevelSelect(1);
            this.updateHint('');
        }
    }
    
    updateHint(text, isSuccess = true) {
        if (this.elements.usernameHint) {
            this.elements.usernameHint.textContent = text;
            this.elements.usernameHint.style.color = isSuccess ? 'var(--color-accent)' : 'var(--color-text-muted)';
        }
    }
    
    setupEventListeners() {
        // Player select change
        if (this.elements.playerSelect) {
            this.elements.playerSelect.addEventListener('change', (e) => {
                const value = e.target.value;
                if (value === 'new') {
                    this.isEditing = false;
                    this.saveActivePlayerId(null); // Clear active profile
                    this.showNewPlayerForm(true);
                    this.updateHint('');
                    this.updateLevelSelect(1);
                } else {
                    this.saveActivePlayerId(value);
                    this.showNewPlayerForm(false);
                    this.loadActiveProfile();
                }
            });
        }
        
        // Edit player button
        if (this.elements.btnEditPlayer) {
            this.elements.btnEditPlayer.addEventListener('click', () => {
                const profile = this.getActiveProfile();
                if (profile) {
                    this.isEditing = true;
                    this.showNewPlayerForm(true);
                    this.elements.usernameInput.value = profile.username;
                    this.elements.usernameInput.placeholder = 'Edit name';
                }
            });
        }
        
        // Delete player button
        if (this.elements.btnDeletePlayer) {
            this.elements.btnDeletePlayer.addEventListener('click', () => {
                const profile = this.getActiveProfile();
                if (profile && confirm(`Remove "${profile.username}" from this device?\n\nTheir score will remain on the leaderboard.`)) {
                    this.deleteProfile(profile.playerId);
                    this.populatePlayerSelect();
                    this.loadActiveProfile();
                }
            });
        }
        
        // Save player button
        if (this.elements.btnSavePlayer) {
            this.elements.btnSavePlayer.addEventListener('click', () => {
                this.savePlayerFromInput();
            });
        }
        
        // Username input enter key
        if (this.elements.usernameInput) {
            this.elements.usernameInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.savePlayerFromInput();
                }
            });
        }
        
        // Leaderboard button on start screen
        if (this.elements.btnLeaderboard) {
            this.elements.btnLeaderboard.addEventListener('click', () => {
                this.showLeaderboard();
            });
        }
        
        // Close leaderboard
        if (this.elements.btnCloseLeaderboard) {
            this.elements.btnCloseLeaderboard.addEventListener('click', () => {
                this.hideLeaderboard();
            });
        }
        
        // View leaderboard from game over
        if (this.elements.btnViewLeaderboard) {
            this.elements.btnViewLeaderboard.addEventListener('click', () => {
                this.hideGameOver();
                this.showLeaderboard();
            });
        }
        
        // View leaderboard from pause menu
        if (this.elements.menuLeaderboard) {
            this.elements.menuLeaderboard.addEventListener('click', () => {
                this.showLeaderboard();
            });
        }
    }
    
    async savePlayerFromInput() {
        const username = this.elements.usernameInput?.value.trim();
        
        if (!username) {
            this.updateHint('Please enter a name', false);
            return;
        }
        
        if (this.isEditing) {
            // Update existing profile
            const profile = this.getActiveProfile();
            if (profile) {
                profile.username = username;
                this.saveProfiles();
                this.populatePlayerSelect();
                this.showNewPlayerForm(false);
                this.updateHint('Name updated!');
                
                // Update username on server (also updates leaderboard)
                await this.updateUsernameOnServer(username);
            }
        } else {
            // Create new profile
            const profile = this.addProfile(username);
            this.populatePlayerSelect();
            this.showNewPlayerForm(false);
            this.updateHint('');
            this.updateLevelSelect(1);
            
            // Check if this player already exists on server
            await this.checkExistingScore();
        }
        
        this.isEditing = false;
    }
    
    getUsername() {
        const profile = this.getActiveProfile();
        return profile ? profile.username : 'Anonymous';
    }
    
    get playerId() {
        return this.activePlayerId;
    }
    
    // Level select management
    updateLevelSelect(maxLevel) {
        if (!this.elements.levelSelect || !this.elements.levelSelectSection) return;
        
        // Only show level select if player has unlocked more than level 1
        if (maxLevel <= 1) {
            this.elements.levelSelectSection.style.display = 'none';
            return;
        }
        
        this.elements.levelSelectSection.style.display = 'block';
        
        // Clear existing options
        this.elements.levelSelect.innerHTML = '';
        
        // Add options for each unlocked level
        for (let i = 1; i <= maxLevel; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = `Level ${i}`;
            this.elements.levelSelect.appendChild(option);
        }
        
        // Default to level 1
        this.elements.levelSelect.value = '1';
    }
    
    getSelectedLevel() {
        if (this.elements.levelSelect) {
            return parseInt(this.elements.levelSelect.value, 10) || 1;
        }
        return 1;
    }
    
    // API calls
    async checkExistingScore() {
        if (!this.activePlayerId) {
            this.updateLevelSelect(1);
            return;
        }
        
        try {
            const response = await fetch(`${this.apiUrl}?action=check_player&player_id=${encodeURIComponent(this.activePlayerId)}`);
            const data = await response.json();
            
            if (data.exists && data.personalBest) {
                this.personalBest = data.personalBest;
                
                // Update local profile
                this.updateProfile(this.activePlayerId, { personalBest: data.personalBest });
                
                const profile = this.getActiveProfile();
                this.updateHint(`Welcome back, ${profile?.username}! Best: Level ${data.personalBest}`);
                this.updateLevelSelect(data.personalBest);
            } else {
                const profile = this.getActiveProfile();
                this.personalBest = profile?.personalBest || 1;
                this.updateHint('');
                this.updateLevelSelect(this.personalBest);
            }
        } catch (error) {
            console.error('Error checking player:', error);
        }
    }
    
    async submitScore(level, spaceEfficiency = 0) {
        if (!this.activePlayerId) return null;
        
        const username = this.getUsername();
        this.currentLevel = level;
        
        try {
            const response = await fetch(`${this.apiUrl}?action=submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    player_id: this.activePlayerId,
                    username: username,
                    level: level,
                    spaceEfficiency: spaceEfficiency
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.personalBest = data.personalBest || level;
                
                // Update local profile
                this.updateProfile(this.activePlayerId, { personalBest: this.personalBest });
                
                return data;
            } else {
                console.error('Score submission failed:', data.error);
                return null;
            }
        } catch (error) {
            console.error('Error submitting score:', error);
            return null;
        }
    }
    
    async updateUsernameOnServer(newUsername) {
        if (!this.activePlayerId) return null;
        
        try {
            const response = await fetch(`${this.apiUrl}?action=update_username`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    player_id: this.activePlayerId,
                    username: newUsername
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                console.error('Username update failed:', data.error);
            }
            
            return data;
        } catch (error) {
            console.error('Error updating username:', error);
            return null;
        }
    }
    
    async fetchLeaderboard(limit = 10) {
        try {
            const response = await fetch(`${this.apiUrl}?action=leaderboard&limit=${limit}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching leaderboard:', error);
            return null;
        }
    }
    
    // UI methods
    async showLeaderboard() {
        if (!this.elements.leaderboardModal) return;
        
        this.elements.leaderboardModal.classList.remove('hidden');
        this.elements.leaderboardList.innerHTML = '<div class="leaderboard-loading">Loading...</div>';
        
        const data = await this.fetchLeaderboard(15);
        
        if (data && data.scores && data.scores.length > 0) {
            const currentUsername = this.getUsername();
            let html = '<div class="leaderboard-table">';
            html += '<div class="leaderboard-header"><span>#</span><span>Player</span><span>Level</span></div>';
            
            data.scores.forEach((score, index) => {
                const isCurrentUser = score.username.toLowerCase() === currentUsername.toLowerCase();
                const rankEmoji = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
                
                html += `<div class="leaderboard-row ${isCurrentUser ? 'current-user' : ''}">
                    <span class="rank">${rankEmoji || score.rank}</span>
                    <span class="name">${this.escapeHtml(score.username)}</span>
                    <span class="level">Level ${score.level}</span>
                </div>`;
            });
            
            html += '</div>';
            this.elements.leaderboardList.innerHTML = html;
            
            if (data.totalPlayers) {
                this.elements.leaderboardTotal.textContent = `${data.totalPlayers} players total`;
            }
        } else {
            this.elements.leaderboardList.innerHTML = '<div class="leaderboard-empty">No scores yet. Be the first!</div>';
            this.elements.leaderboardTotal.textContent = '';
        }
    }
    
    hideLeaderboard() {
        if (this.elements.leaderboardModal) {
            this.elements.leaderboardModal.classList.add('hidden');
        }
    }
    
    async showGameOver(level, reason = 'Item fell out!') {
        // Submit score first
        const result = await this.submitScore(level);
        
        if (this.elements.gameoverModal) {
            this.elements.gameoverLevel.textContent = level;
            
            let message = reason;
            if (result && result.isNewRecord) {
                message += ' New personal best! 🎉';
            } else if (result && result.personalBest > level) {
                message += ` Your best: Level ${result.personalBest}`;
            }
            if (result && result.rank) {
                message += ` (Rank #${result.rank})`;
            }
            
            this.elements.gameoverMessage.textContent = message;
            this.elements.gameoverModal.classList.remove('hidden');
        }
    }
    
    hideGameOver() {
        if (this.elements.gameoverModal) {
            this.elements.gameoverModal.classList.add('hidden');
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
<<<<<<<< HEAD:scripts/HighScoreManager.js
========
</script>
>>>>>>>> origin/main:scripts/HighScoreManager.php
