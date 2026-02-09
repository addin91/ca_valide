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
