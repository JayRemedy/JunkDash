<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>JunkDash | JunkDoctors</title>
    <meta name="description" content="Pack, haul, and deliver in JunkDash, a fast 3D truck loading game by JunkDoctors.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Instrument+Serif&display=block" rel="stylesheet">
    
    <!-- Babylon.js -->
    <script src="https://cdn.babylonjs.com/babylon.js"></script>
    <script src="https://cdn.babylonjs.com/materialsLibrary/babylonjs.materials.min.js"></script>
    <script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>
    <!-- Havok Physics (better performance and collision detection) -->
    <script src="https://cdn.babylonjs.com/havok/HavokPhysics_umd.js"></script>
    
    <!-- Inline Styles -->
    <?php include __DIR__ . '/styles/main.php'; ?>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="brand-mark">
                <span class="brand-icon">🚛</span>
                <h1 class="brand-title">JunkDash</h1>
                <p class="brand-subtitle">by JunkDoctors</p>
            </div>
            <div class="loading-bar-container">
                <div class="loading-bar" id="loading-bar"></div>
            </div>
            <p class="loading-text" id="loading-text">Preparing your truck...</p>
        </div>
    </div>

    <!-- Game Canvas -->
    <canvas id="game-canvas"></canvas>

    <!-- Minimal UI Overlay -->
    <div id="ui-overlay" class="ui-overlay hidden">
        <!-- Top Bar -->
        <header class="ui-header">
            <div class="score-display">
                <span class="score-label">Load Packed</span>
                <span class="score-value" id="space-score">0%</span>
                <span class="score-subvalue" id="space-volume">0/25 yd³</span>
            </div>
            <div class="level-display">
                <span class="level-label">Level</span>
                <span class="level-value" id="current-level">1</span>
            </div>
            <button class="btn-icon" id="btn-music" aria-label="Toggle Music" title="Toggle Music">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                </svg>
            </button>
            <button class="btn-icon" id="btn-menu" aria-label="Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </header>

        <!-- Pickup Location Hint (shows when away from pickup) -->
        <div id="pickup-hint" class="pickup-hint" style="display: none;">
            <div class="pickup-hint-icon">📦</div>
            <div class="pickup-hint-text">Drive to <span class="pickup-color">orange beacon</span> to pick up items</div>
        </div>
        
        <!-- Pickup Items List (only shows when at pickup) -->
        <div class="item-queue item-panel pickup-items-panel away-from-pickup" id="pickup-item-panel">
            <div class="queue-title">Pickup Items</div>
            <div class="queue-items" id="queue-items"></div>
        </div>


        <!-- Action Hints -->
        <div class="action-hints" id="action-hints">
            <span class="hint-text">Drive to <span class="pickup-color">orange beacon</span> to pick up items</span>
        </div>

        <!-- Minimap -->
        <div class="minimap-container" id="minimap-container">
            <canvas id="minimap-canvas" width="200" height="200"></canvas>
            <canvas id="minimap-pins" width="200" height="200"></canvas>
        </div>
        
        <!-- Speedometer & Gear -->
        <div class="speed-cluster" id="speed-cluster">
            <div class="speedometer">
                <span class="speed-value" id="speed-value">0</span>
                <span class="speed-unit">MPH</span>
            </div>
            <div class="gear-indicator">
                <span class="gear-value" id="gear-value">N</span>
            </div>
        </div>

        <!-- Mobile Touch Controls -->
        <div class="mobile-controls" id="mobile-controls">
            <div class="touch-joystick left" id="joystick-move">
                <div class="joystick-base">
                    <div class="joystick-stick" id="stick-move"></div>
                </div>
                <span class="joystick-label">DRIVE</span>
            </div>
            <div class="touch-joystick right" id="joystick-look">
                <div class="joystick-base">
                    <div class="joystick-stick" id="stick-look"></div>
                </div>
                <span class="joystick-label">LOOK</span>
            </div>
        </div>
    </div>

    <!-- Results Modal -->
    <div id="results-modal" class="modal hidden">
        <div class="modal-content results-content">
            <h2 class="results-title">Load Complete!</h2>
            <div class="results-stats">
                <div class="stat">
                    <span class="stat-value" id="result-efficiency">95%</span>
                    <span class="stat-label">Load Packed</span>
                </div>
            </div>
            <div class="results-actions">
                <button class="btn-primary" id="btn-next-level">Next Load</button>
                <button class="btn-secondary" id="btn-retry">Try Again</button>
            </div>
        </div>
    </div>

    <!-- Main Menu Modal -->
    <div id="menu-modal" class="modal hidden">
        <div class="modal-content menu-content">
            <h2 class="menu-title">Paused</h2>
            <nav class="menu-nav">
                <button class="menu-item" id="menu-resume">Resume <span class="menu-shortcut">Esc</span></button>
                <button class="menu-item" id="menu-restart">Restart Level <span class="menu-shortcut">R</span></button>
                <button class="menu-item" id="menu-leaderboard">🏆 Leaderboard</button>
                <button class="menu-item" id="menu-quit">Leave <span class="menu-shortcut">L</span></button>
            </nav>
        </div>
    </div>

    <!-- Start Screen -->
    <div id="start-screen" class="start-screen hidden">
        <div class="start-content">
            <div class="start-brand">
                <h1 class="start-title">JunkDash</h1>
                <p class="start-tagline">The art of efficient loading</p>
            </div>
            <div class="player-section" id="player-section">
                <div class="player-select-row">
                    <select id="player-select" class="player-select">
                        <option value="new">+ New Player</option>
                    </select>
                    <button class="btn-edit-player" id="btn-edit-player" title="Edit name">✏️</button>
                    <button class="btn-delete-player" id="btn-delete-player" title="Remove player">🗑️</button>
                </div>
                <div class="new-player-row hidden" id="new-player-row">
                    <input type="text" id="username-input" class="username-input" placeholder="Enter your name" maxlength="20" autocomplete="off">
                    <button class="btn-save-player" id="btn-save-player">Save</button>
                </div>
                <p class="username-hint" id="username-hint"></p>
            </div>
            <div class="level-select-section" id="level-select-section" style="display: none;">
                <label class="level-select-label">Start at Level</label>
                <select id="level-select" class="level-select">
                    <option value="1">Level 1</option>
                </select>
            </div>
            <div class="start-actions">
                <button class="btn-start" id="btn-start">
                    <span>Play</span>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5,3 19,12 5,21"></polygon>
                    </svg>
                </button>
                <button class="btn-leaderboard" id="btn-leaderboard">🏆</button>
            </div>
            <p class="start-hint">Best on desktop with a mouse</p>
        </div>
    </div>

    <!-- Game Over Modal -->
    <div id="gameover-modal" class="modal hidden">
        <div class="modal-content gameover-content">
            <h2 class="gameover-title">Game Over</h2>
            <div class="gameover-stats">
                <div class="gameover-level">
                    <span class="gameover-level-value" id="gameover-level">1</span>
                    <span class="gameover-level-label">Level Reached</span>
                </div>
                <p class="gameover-message" id="gameover-message"></p>
            </div>
            <div class="gameover-actions">
                <button class="btn-primary" id="btn-play-again">Play Again</button>
                <button class="btn-secondary" id="btn-view-leaderboard">🏆 Leaderboard</button>
            </div>
        </div>
    </div>

    <!-- Leaderboard Modal -->
    <div id="leaderboard-modal" class="modal hidden">
        <div class="modal-content leaderboard-content">
            <h2 class="leaderboard-title">🏆 Leaderboard</h2>
            <div class="leaderboard-list" id="leaderboard-list">
                <div class="leaderboard-loading">Loading...</div>
            </div>
            <p class="leaderboard-total" id="leaderboard-total"></p>
            <div class="leaderboard-actions">
                <button class="btn-secondary" id="btn-close-leaderboard">Close</button>
            </div>
        </div>
    </div>

    <!-- Game Scripts (PHP includes for no-cache development) -->
    <?php include __DIR__ . '/scripts/Coords.php'; ?>
    <?php include __DIR__ . '/scripts/AudioManager.php'; ?>
    <?php include __DIR__ . '/scripts/SceneManager.php'; ?>
    <?php include __DIR__ . '/scripts/PhysicsSystem.php'; ?>
    <?php include __DIR__ . '/scripts/Truck.php'; ?>
    <?php include __DIR__ . '/scripts/ItemManager.php'; ?>
    <?php include __DIR__ . '/scripts/InputSystem.php'; ?>
    <?php include __DIR__ . '/scripts/UIManager.php'; ?>
    <?php include __DIR__ . '/scripts/LevelManager.php'; ?>
    <?php include __DIR__ . '/scripts/HighScoreManager.php'; ?>
    <?php include __DIR__ . '/scripts/Game.php'; ?>
    <?php include __DIR__ . '/scripts/main.php'; ?>
</body>
</html>

