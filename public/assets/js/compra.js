// Detalle dinámico de la compra: agregar/quitar líneas y calcular subtotales.
// El total definitivo lo recalcula el servidor; esto es solo de ayuda visual.
(function () {
    const tabla = document.querySelector('#detalle tbody');
    const totalCelda = document.getElementById('total');
    const formato = new Intl.NumberFormat('es-PY');

    function recalcular() {
        let total = 0;
        tabla.querySelectorAll('tr').forEach(function (fila) {
            const cant = parseInt(fila.querySelector('.cantidad').value, 10) || 0;
            const precio = parseInt(fila.querySelector('.precio').value, 10) || 0;
            const sub = cant * precio;
            fila.querySelector('.subtotal').textContent = formato.format(sub);
            const opcion = fila.querySelector('.producto').selectedOptions[0];
            fila.querySelector('.stock').textContent = opcion && opcion.value ? opcion.dataset.stock : '-';
            total += sub;
        });
        totalCelda.textContent = formato.format(total);
    }

    tabla.addEventListener('input', recalcular);
    tabla.addEventListener('change', function (ev) {
        // Al elegir un producto se sugiere su último precio de compra
        if (ev.target.classList.contains('producto')) {
            const opcion = ev.target.selectedOptions[0];
            const precio = ev.target.closest('tr').querySelector('.precio');
            if (opcion && opcion.value && !precio.value) {
                precio.value = opcion.dataset.precio;
            }
        }
        recalcular();
    });
    tabla.addEventListener('click', function (ev) {
        if (ev.target.classList.contains('quitar') && tabla.rows.length > 1) {
            ev.target.closest('tr').remove();
            recalcular();
        }
    });
    document.getElementById('agregar').addEventListener('click', function () {
        const nueva = tabla.rows[0].cloneNode(true);
        nueva.querySelector('.producto').value = '';
        nueva.querySelector('.cantidad').value = 1;
        nueva.querySelector('.precio').value = '';
        tabla.appendChild(nueva);
        recalcular();
    });
    recalcular();
})();
