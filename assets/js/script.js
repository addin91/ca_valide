document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('input[type="number"]');

    inputs.forEach(input => {
        input.addEventListener('input', function () {
            let val = parseFloat(this.value);
            if (val < 0) this.value = 0;
            if (val > 20) this.value = 20;
        });

        // Validation visuelle
        input.addEventListener('change', function () {
            if (this.value !== '') {
                this.style.borderColor = 'var(--primary-color)';
            } else {
                this.style.borderColor = 'var(--border-color)';
            }
        });


    });


    document.querySelector("select.ue-selector").addEventListener("change", (e) => {
        for(const el of document.getElementsByClassName(`child-of-${e.target.name.match(/\[(\d+)\]/)[1]}`)){
            el.style.display = "none";
        }
        document.getElementById(`child-${e.target.value}`).style.display = "block";
        console.log(e.target.name.match(/\[(\d+)\]/)[1])	
        console.log(`child-${e.target.value}`)
    })

});


    document.addEventListener('DOMContentLoaded', function() {
        window.toggleLabel = function(chk, labelId) {
            const lbl = document.getElementById(labelId);
            if(chk.checked) lbl.classList.add('text-green-active');
            else lbl.classList.remove('text-green-active');
        };

        // UE Child Selection Logic
        document.querySelectorAll("select.ue-selector").forEach(sel => {
            sel.addEventListener("change", (e) => {
                const parentId = e.target.name.match(/\[(\d+)\]/)[1];
                document.querySelectorAll(`.child-of-${parentId}`).forEach(el => el.style.display = "none");
                const val = e.target.value;
                if(val) {
                    const child = document.getElementById(`child-${val}`);
                    if(child) child.style.display = "block";
                }
            });
        });
    });