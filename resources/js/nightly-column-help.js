export function initNightlyColumnHelp(workspace) {
    let active = null;
    let pinned = false;
    const buttons = [...workspace.querySelectorAll('[data-nightly-column-help]')];
    const tips = [...new Set(buttons.map((button) => document.getElementById(button.getAttribute('popovertarget'))))];
    const tipFor = (button) => document.getElementById(button.getAttribute('popovertarget'));
    if (!HTMLElement.prototype.showPopover) {
        buttons.forEach((button) => { button.title = tipFor(button).textContent.trim(); });
        tips.forEach((tip) => { tip.hidden = true; });
        return;
    }
    const close = () => {
        const tip = active && tipFor(active);
        active = null;
        pinned = false;
        if (tip?.matches(':popover-open')) tip.hidePopover();
    };
    const open = (button) => {
        if (active !== button) close();
        active = button;
        const tip = tipFor(button);
        if (!tip.matches(':popover-open')) tip.showPopover();
        // A top-layer popover escapes the table scroller; clamp it to the viewport.
        const anchor = button.getBoundingClientRect();
        const box = tip.getBoundingClientRect();
        const left = Math.max(12, Math.min(anchor.left, innerWidth - box.width - 12));
        const below = anchor.bottom + 8;
        const top = below + box.height <= innerHeight - 12 ? below : Math.max(12, anchor.top - box.height - 8);
        tip.style.left = `${left}px`;
        tip.style.top = `${Math.min(top, Math.max(12, innerHeight - box.height - 12))}px`;
    };
    const leave = (event) => {
        const tip = active && tipFor(active);
        if (!active || pinned || active === document.activeElement || active.contains(event.relatedTarget) || tip.contains(event.relatedTarget)) return;
        close();
    };
    buttons.forEach((button) => {
        button.addEventListener('mouseenter', () => open(button));
        button.addEventListener('mouseleave', leave);
        button.addEventListener('focus', () => open(button));
        button.addEventListener('blur', () => { if (!pinned && !tipFor(button).matches(':hover')) close(); });
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (active === button && pinned) close();
            else { open(button); pinned = true; }
        });
    });
    tips.forEach((tip) => {
        tip.addEventListener('mouseleave', leave);
        tip.addEventListener('toggle', () => { if (active && tip === tipFor(active) && !tip.matches(':popover-open')) close(); });
    });
    document.addEventListener('pointerdown', (event) => {
        if (active && !active.contains(event.target) && !tipFor(active).contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    document.addEventListener('scroll', (event) => {
        if (active && !tipFor(active).contains(event.target)) close();
    }, true);
    window.addEventListener('resize', close);
}
