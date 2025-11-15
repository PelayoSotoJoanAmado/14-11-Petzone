/**
 * Handler del Formulario de Reserva - PetZone
 * Maneja el envío del formulario de citas
 */

document.addEventListener('DOMContentLoaded', function() {
    const formularioReserva = document.getElementById('formularioReserva');
    
    if (formularioReserva) {
        formularioReserva.addEventListener('submit', handleFormularioReserva);
    }
});

/**
 * Manejar envío del formulario de reserva
 */
async function handleFormularioReserva(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnTextoOriginal = submitBtn.textContent;
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';
    
    // Obtener datos del formulario
    const formData = new FormData(form);
    
    // Convertir a objeto
    const data = {
        nombre: formData.get('nombre'),
        correo: formData.get('correo'),
        telefono: formData.get('telefono'),
        servicio: formData.get('servicio'),
        mensaje: formData.get('mensaje') || ''
    };
    
    // Validaciones básicas 
    if (!data.nombre || !data.correo || !data.telefono || !data.servicio) {
        mostrarMensaje('Por favor completa todos los campos requeridos', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = btnTextoOriginal;
        return;
    }
    
    if (!validarEmail(data.correo)) {
        mostrarMensaje('Por favor ingresa un correo electrónico válido', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = btnTextoOriginal;
        return;
    }
    
    if (!validarTelefono(data.telefono)) {
        mostrarMensaje('Por favor ingresa un número de teléfono válido', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = btnTextoOriginal;
        return;
    }
    
    try {
        const response = await fetch('../api/citas.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarMensaje(
                `¡Cita registrada exitosamente! Tu código de cita es: ${result.codigo_cita}. Te contactaremos pronto.`,
                'success'
            );
            form.reset();
            
            // Opcional: Mostrar modal con código de cita
            mostrarModalConfirmacion(result.codigo_cita);
        } else {
            mostrarMensaje(result.message || 'Error al registrar la cita', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarMensaje('Error de conexión. Por favor intenta nuevamente.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = btnTextoOriginal;
    }
}

/**
 * Validar formato de email
 */
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validar formato de teléfono (Perú)
 */
function validarTelefono(telefono) {
    // Formato: 9 dígitos (celular peruano) o 7-9 dígitos
    const regex = /^[0-9]{7,9}$/;
    return regex.test(telefono.replace(/\s/g, ''));
}

/**
 * Mostrar mensaje de notificación
 */
function mostrarMensaje(mensaje, tipo = 'info') {
    // Crear elemento de notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion--${tipo}`;
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${tipo === 'success' ? '#4CAF50' : tipo === 'error' ? '#f44336' : '#2196F3'};
        color: white;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        max-width: 400px;
    `;
    
    notificacion.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 20px;">
                ${tipo === 'success' ? '✓' : tipo === 'error' ? '✕' : 'ℹ'}
            </span>
            <span>${mensaje}</span>
        </div>
    `;
    
    document.body.appendChild(notificacion);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        notificacion.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notificacion.remove(), 300);
    }, 5000);
}

/**
 * Mostrar modal de confirmación con código de cita
 */
function mostrarModalConfirmacion(codigoCita) {
    const modal = document.createElement('div');
    modal.className = 'modal-confirmacion';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
        animation: fadeIn 0.3s ease-out;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        ">
            <div style="font-size: 60px; color: #4CAF50; margin-bottom: 20px;">✓</div>
            <h2 style="color: #333; margin-bottom: 15px;">¡Cita Registrada!</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Tu solicitud ha sido recibida exitosamente.
            </p>
            <div style="
                background: #f5f5f5;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            ">
                <p style="color: #999; font-size: 12px; margin-bottom: 5px;">Código de Cita:</p>
                <p style="color: #333; font-size: 24px; font-weight: bold; font-family: monospace;">
                    ${codigoCita}
                </p>
            </div>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                Guarda este código. Te contactaremos pronto para confirmar tu cita.
            </p>
            <button onclick="this.closest('.modal-confirmacion').remove()" style="
                background: #23906F;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                transition: background 0.3s;
            " onmouseover="this.style.background='#1a6d54'" 
               onmouseout="this.style.background='#23906F'">
                Cerrar
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Cerrar al hacer clic fuera del modal
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Agregar estilos de animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);