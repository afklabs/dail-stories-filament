{{-- resources/views/components/duration-selector.blade.php --}}
<div class="flex gap-2 mt-2">
    <button type="button" 
            onclick="setDuration(1)" 
            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
        1 Hour
    </button>
    <button type="button" 
            onclick="setDuration(6)" 
            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
        6 Hours
    </button>
    <button type="button" 
            onclick="setDuration(24)" 
            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
        1 Day
    </button>
    <button type="button" 
            onclick="setDuration(168)" 
            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
        1 Week
    </button>
    <button type="button" 
            onclick="setDuration(720)" 
            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
        1 Month
    </button>
</div>

<script>
function setDuration(hours) {
    const activeFromInput = document.querySelector('[name="active_from"]');
    if (activeFromInput && activeFromInput.value) {
        const activeFrom = new Date(activeFromInput.value);
        const activeUntil = new Date(activeFrom.getTime() + (hours * 60 * 60 * 1000));
        
        const activeUntilInput = document.querySelector('[name="active_until"]');
        if (activeUntilInput) {
            // Format datetime for input
            const formattedDate = activeUntil.toISOString().slice(0, 16);
            activeUntilInput.value = formattedDate;
            
            // Trigger change event
            activeUntilInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}
</script>