<script>
/**
 * PhysicsSystem - Manages physics simulation and stability checks
 */
class PhysicsSystem {
    constructor(scene) {
        this.scene = scene;
        this.onItemFallOut = null; // Callback when item falls out of truck
    }
    
    setFallOutCallback(callback) {
        this.onItemFallOut = callback;
    }
    
    isItemSettled(mesh) {
        if (!mesh.physicsAggregate || !mesh.physicsAggregate.body) return true;
        const vel = mesh.physicsAggregate.body.getLinearVelocity();
        const angVel = mesh.physicsAggregate.body.getAngularVelocity();
        return vel.length() < 0.05 && angVel.length() < 0.05;
    }
    
    checkFallenItems(items, truckBounds) {
        let newlyFallen = 0;
        
        items.forEach(item => {
            if (item.isFallen) return; // Already marked as fallen
            
            if (item.mesh) {
                const pos = item.mesh.position;
                
                // Check if item has fallen out of truck
                const fellBelow = pos.y < 0.5; // Below truck floor level
                const fellOffSide = truckBounds && (
                    pos.x < truckBounds.minX - 1 || 
                    pos.x > truckBounds.maxX + 1 ||
                    pos.z < truckBounds.minZ - 1 ||
                    pos.z > truckBounds.maxZ + 1
                );
                const fellOffBack = truckBounds && pos.z > truckBounds.maxZ + 2; // Fell out the back
                
                if (fellBelow || fellOffSide || fellOffBack) {
                    item.isFallen = true;
                    newlyFallen++;
                    
                    // Trigger callback
                    if (this.onItemFallOut) {
                        this.onItemFallOut(item);
                    }
                }
            }
        });
        
        return newlyFallen;
    }
    
    getFallenCount(items) {
        return items.filter(item => item.isFallen).length;
    }
}
</script>
