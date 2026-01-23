<script>
/**
 * InputSystem - Handles mouse, keyboard, and touch input
 */
class InputSystem {
    constructor(game) {
        this.game = game;
        this.scene = game.scene;
        this.canvas = game.canvas;
        this.camera = game.sceneManager.camera;
        
        this.rotationAngle = 0;
        this.pointerX = 0;
        this.pointerY = 0;
        
        // Mobile joystick state
        this.moveJoystick = { active: false, x: 0, y: 0, touchId: null };
        this.lookJoystick = { active: false, x: 0, y: 0, touchId: null };
        
        // Create invisible drag plane at truck height (for item placement raycasting)
        this.dragPlane = BABYLON.MeshBuilder.CreateGround('dragPlane', { width: 100, height: 100 }, this.scene);
        this.dragPlane.position.y = game.truck.cargoFloorHeight + 0.5;
        this.dragPlane.isVisible = false; // Completely hide from rendering
        this.dragPlane.isPickable = true; // But still allow raycasting
    }
    
    init() {
        // Pointer events
        this.scene.onPointerObservable.add((info) => this.handlePointer(info));
        
        // Keyboard
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));
        document.addEventListener('keyup', (e) => this.handleKeyUp(e));
        
        // Mouse wheel for rotation
        this.canvas.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });
        
        // Prevent context menu
        this.canvas.addEventListener('contextmenu', (e) => e.preventDefault());
        
        // Mobile touch controls
        this.initMobileControls();
    }
    
    initMobileControls() {
        const moveBase = document.querySelector('#joystick-move .joystick-base');
        const lookBase = document.querySelector('#joystick-look .joystick-base');
        const moveStick = document.getElementById('stick-move');
        const lookStick = document.getElementById('stick-look');
        
        if (!moveBase || !lookBase) return;
        
        // Move joystick (left side - controls W/A/S/D)
        this.setupJoystick(moveBase, moveStick, this.moveJoystick, (x, y) => {
            const truck = this.game.truck;
            // Y axis controls forward/backward (W/S)
            truck.keys.w = y < -0.3;
            truck.keys.s = y > 0.3;
            // X axis controls turning (A/D)
            truck.keys.a = x < -0.3;
            truck.keys.d = x > 0.3;
        });
        
        // Look joystick (right side - controls camera)
        this.setupJoystick(lookBase, lookStick, this.lookJoystick, (x, y) => {
            const sm = this.game.sceneManager;
            // Apply camera offset based on joystick position
            sm.isTouchLooking = (x !== 0 || y !== 0);
            sm.touchAngleOffset = -x * 0.8; // Left/right (negative because camera alpha is inverted)
            sm.touchBetaOffset = -y * 0.4;  // Up/down
        });
    }
    
    setupJoystick(base, stick, state, onMove) {
        const maxDistance = 35; // Max pixels the stick can move from center
        
        const updateStick = (clientX, clientY) => {
            const rect = base.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            let dx = clientX - centerX;
            let dy = clientY - centerY;
            
            // Clamp to max distance
            const distance = Math.sqrt(dx * dx + dy * dy);
            if (distance > maxDistance) {
                dx = (dx / distance) * maxDistance;
                dy = (dy / distance) * maxDistance;
            }
            
            // Update visual
            stick.style.transform = `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px))`;
            stick.classList.add('active');
            
            // Normalize to -1 to 1
            state.x = dx / maxDistance;
            state.y = dy / maxDistance;
            
            onMove(state.x, state.y);
        };
        
        const resetStick = () => {
            stick.style.transform = 'translate(-50%, -50%)';
            stick.classList.remove('active');
            state.active = false;
            state.x = 0;
            state.y = 0;
            state.touchId = null;
            onMove(0, 0);
        };
        
        // Touch events
        base.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const touch = e.changedTouches[0];
            state.active = true;
            state.touchId = touch.identifier;
            updateStick(touch.clientX, touch.clientY);
        }, { passive: false });
        
        base.addEventListener('touchmove', (e) => {
            e.preventDefault();
            for (let touch of e.changedTouches) {
                if (touch.identifier === state.touchId) {
                    updateStick(touch.clientX, touch.clientY);
                    break;
                }
            }
        }, { passive: false });
        
        base.addEventListener('touchend', (e) => {
            for (let touch of e.changedTouches) {
                if (touch.identifier === state.touchId) {
                    resetStick();
                    break;
                }
            }
        });
        
        base.addEventListener('touchcancel', resetStick);
        
        // Mouse events (for testing on desktop)
        let mouseDown = false;
        base.addEventListener('mousedown', (e) => {
            mouseDown = true;
            state.active = true;
            updateStick(e.clientX, e.clientY);
        });
        
        window.addEventListener('mousemove', (e) => {
            if (mouseDown && state.active) {
                updateStick(e.clientX, e.clientY);
            }
        });
        
        window.addEventListener('mouseup', () => {
            if (mouseDown) {
                mouseDown = false;
                resetStick();
            }
        });
    }
    
    handlePointer(info) {
        const itemManager = this.game.itemManager;
        
        if (info.type === BABYLON.PointerEventTypes.POINTERMOVE) {
            this.pointerX = info.event.clientX;
            this.pointerY = info.event.clientY;
            
            // Update drag plane to follow truck
            this.dragPlane.position.x = this.game.truck.position.x;
            this.dragPlane.position.z = this.game.truck.position.z;
            this.dragPlane.position.y = this.game.truck.cargoFloorHeight + 0.5;
            
            // Only show preview when at pickup location
            if (!this.game.isAtPickup && itemManager.previewMesh) {
                itemManager.clearPreview();
                return;
            }
            
            // Update preview position if item selected
            if (itemManager.selectedItemId && itemManager.previewMesh) {
                // Use infinite horizontal plane intersection (prevents snapping and occlusion issues)
                const ray = this.scene.createPickingRay(this.pointerX, this.pointerY, BABYLON.Matrix.Identity(), this.camera);
                const planeY = this.game.truck.getFloorTopY();
                
                if (ray.direction.y !== 0) {
                    const t = (planeY - ray.origin.y) / ray.direction.y;
                    if (t > 0) {
                        const hitPoint = ray.origin.add(ray.direction.scale(t));
                        // Allow placement anywhere - physics will decide if it falls
                        itemManager.updatePreview(hitPoint.x, hitPoint.z, this.rotationAngle);
                    }
                }
            }
        }
        
        if (info.type === BABYLON.PointerEventTypes.POINTERDOWN && info.event.button === 0) {
            const pickResult = info.pickInfo;
            this.pointerX = info.event.clientX;
            this.pointerY = info.event.clientY;
            
            if (pickResult.hit && pickResult.pickedMesh) {
                const meshName = pickResult.pickedMesh.name;
                
                // Check if clicking on a ground item to pick it up (only at pickup location)
                if (this.game.isAtPickup && !itemManager.selectedItemId) {
                    const groundItem = itemManager.getGroundItem(pickResult.pickedMesh);
                    if (groundItem) {
                        itemManager.pickupGroundItem(groundItem);
                        const hit = this.scene.pick(this.pointerX, this.pointerY, (m) => m === this.dragPlane);
                        if (hit && hit.hit) {
                            itemManager.updatePreview(hit.pickedPoint.x, hit.pickedPoint.z, this.rotationAngle);
                        }
                        return;
                    }
                }
                
                if (itemManager.selectedItemId) {
                    // Place at the current preview position (ray-plane intersection)
                    const ray = this.scene.createPickingRay(this.pointerX, this.pointerY, BABYLON.Matrix.Identity(), this.camera);
                    const planeY = this.game.truck.getFloorTopY();
                    
                    if (ray.direction.y !== 0) {
                        const t = (planeY - ray.origin.y) / ray.direction.y;
                        if (t > 0) {
                            const hitPoint = ray.origin.add(ray.direction.scale(t));
                            if (itemManager.isValidPlacement(itemManager.previewMesh)) {
                                const placed = itemManager.placeItem(hitPoint.x, hitPoint.z, this.rotationAngle);
                                if (placed) {
                                    this.game.uiManager.markItemPlaced(placed.id);
                                }
                            } else {
                                this.game.audioManager.playSound('error');
                            }
                        }
                    }
                }
            }
        }
        
        // Right click cancels selection (returns item to ground if picked up)
        if (info.type === BABYLON.PointerEventTypes.POINTERDOWN && info.event.button === 2) {
            if (itemManager.selectedItemId || itemManager.heldGroundItem) {
                itemManager.cancelPickup();
                this.game.audioManager.playSound('error');
            }
        }
    }
    
    handleKeyDown(e) {
        // Handle pause menu shortcuts
        if (this.game.uiManager.isMenuVisible()) {
            this.handleMenuKeyDown(e);
            return;
        }
        
        if (e.key === 'r' || e.key === 'R') {
            this.rotationAngle += Math.PI / 2;
        }
        
        // H key starts horn (held)
        if ((e.key === 'h' || e.key === 'H') && this.game.isRunning) {
            this.game.audioManager.startHorn();
        }
        
        // P key toggles pause menu
        if (e.key === 'p' || e.key === 'P') {
            this.game.uiManager.toggleMenu();
        }
        
        // 0 key teleports truck to test area (near origin where houses are)
        if (e.key === '0' && this.game.isRunning) {
            const truck = this.game.truck;
            truck.position.x = 0;
            truck.position.z = 0;
            truck.rotation = 0;
            truck.speed = 0;
            truck.applyTransform(); // Update visual meshes
            console.log('🧪 TEST: Teleported truck to origin (0, 0)');
        }
        
        // 9 key toggles collision debug visualization
        if (e.key === '9' && this.game.isRunning) {
            const truck = this.game.truck;
            if (truck.debugEnabled) {
                truck.hideCollisionDebug();
                console.log('🧪 DEBUG: Collision boxes HIDDEN');
            } else {
                truck.showCollisionDebug();
                console.log('🧪 DEBUG: Collision boxes SHOWN (green=truck, red=houses)');
            }
        }
        
        if (e.key === 'Escape') {
            if (this.game.itemManager.selectedItemId) {
                this.game.itemManager.selectedItemId = null;
                this.game.itemManager.clearPreview();
            } else if (this.game.isRunning) {
                this.game.uiManager.toggleMenu();
            }
        }
    }
    
    handleKeyUp(e) {
        // H key stops horn
        if (e.key === 'h' || e.key === 'H') {
            this.game.audioManager.stopHorn();
        }
    }

    handleMenuKeyDown(e) {
        const ui = this.game.uiManager;
        
        // If there's a pending confirmation
        if (ui.pendingMenuAction) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const action = ui.pendingMenuAction;
                ui.hideMenu();
                if (action === 'restart') {
                    this.game.restartLevel();
                } else if (action === 'quit') {
                    this.game.quit();
                }
                return;
            } else if (e.key === 'Escape') {
                ui.clearPendingAction();
                return;
            }
            return;
        }
        
        // R for restart (with confirmation)
        if (e.key === 'r' || e.key === 'R') {
            ui.showPendingAction('restart', 'Press Enter to restart, Esc to cancel');
            return;
        }
        
        // L for quit to title (with confirmation)
        if (e.key === 'l' || e.key === 'L') {
            ui.showPendingAction('quit', 'Press Enter to quit, Esc to cancel');
            return;
        }
        
        // Escape closes menu (if no pending action)
        if (e.key === 'Escape') {
            ui.hideMenu();
            this.game.resume();
        }
    }
    
    handleWheel(e) {
        if (this.game.itemManager.selectedItemId) {
            e.preventDefault();
            const step = Math.PI / 12;
            this.rotationAngle += e.deltaY > 0 ? step : -step;
        }
    }
}
</script>
