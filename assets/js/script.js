document.addEventListener('DOMContentLoaded', function () {

    // Inputs number
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function () {
            let val = parseFloat(this.value);
            if (val < 0) this.value = 0;
            if (val > 20) this.value = 20;
        });

        input.addEventListener('change', function () {
            this.style.borderColor = this.value !== ''
                ? 'var(--primary-color)'
                : 'var(--border-color)';
        });
    });

    // Toggle label
    window.toggleLabel = function(chk, labelId) {
        const chks = document.querySelectorAll('input[type="checkbox"]');
        const lbls = document.querySelectorAll('span.toggle-label');
        const lbl = document.getElementById(labelId);

        if(chk.checked) lbl.classList.add('text-green-active');
        else lbl.classList.remove('text-green-active');

        chks.forEach(c => { if(c !== chk) c.checked = false; });
        lbls.forEach(l => { if(l !== lbl) l.classList.remove('text-green-active'); });
    };

    // UE selector
    document.querySelectorAll("select.ue-selector").forEach(sel => {
        sel.addEventListener("change", (e) => {
            const match = e.target.name?.match(/\[(\d+)\]/);
            if(!match) return;

            const parentId = match[1];
            document.querySelectorAll(`.child-of-${parentId}`)
                .forEach(el => el.style.display = "none");

            const child = document.getElementById(`child-${e.target.value}`);
            if(child) child.style.display = "block";
        });
    });

});
