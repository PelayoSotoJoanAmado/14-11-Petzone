/**
 * Sistema de Productos Dinámicos - PetZone
 * Carga productos desde la base de datos
 */

const API_URL = '../api/productos.php';
const PRODUCTS_PER_PAGE = 6;
let allProducts = [];
let filteredProducts = [];
let currentFilter = 'todos';
let displayedCount = PRODUCTS_PER_PAGE;


// INICIALIZACIÓN

document.addEventListener('DOMContentLoaded', () => {
    loadProductsFromDB();
    setupFilters();
    setupShowMoreButton();
});


// CARGAR PRODUCTOS DESDE LA BASE DE DATOS

async function loadProductsFromDB() {
    try {
        showLoading();
        
        const response = await fetch(`${API_URL}?action=list`);
        const data = await response.json();
        
        if (data.success && data.productos.length > 0) {
            allProducts = data.productos;
            filteredProducts = [...allProducts];
            renderProducts();
            updateShowMoreButton();
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Error al cargar productos:', error);
        showErrorState();
    }
}


// RENDERIZAR PRODUCTOS

function renderProducts() {
    const grid = document.getElementById('productGrid');
    
    if (filteredProducts.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 4rem 2rem;">
                <span class="material-icons" style="font-size: 5rem; color: #ddd;">inventory_2</span>
                <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">
                    No hay productos en esta categoría
                </p>
            </div>
        `;
        return;
    }
    
    const productsToShow = filteredProducts.slice(0, displayedCount);
    
    grid.innerHTML = productsToShow.map(producto => `
        <article class="product" data-category="${producto.categoria_slug}" data-id="${producto.id}" data-precio="${producto.precio}" data-stock="${producto.stock}">
            <div class="product__image">
                <img src="../${producto.imagen}" alt="${producto.nombre}" class="product__img" onerror="this.src='../IMG/no-image.png'">
                ${producto.destacado == 1 ? '<span class="product__badge">⭐ Destacado</span>' : ''}
            </div>
            <div class="product__info">
                <h3 class="product__name">${producto.nombre}</h3>
                <p class="product__description">${producto.descripcion || ''}</p>
                <div class="product__footer">
                    <div class="product__price-row">
                        <span class="product__price">S/. ${parseFloat(producto.precio).toFixed(2)}</span>
                    </div>
                    <span class="product__stock ${producto.stock < 10 ? 'low-stock' : ''}">
                        Stock: ${producto.stock} unidades
                    </span>
                    ${producto.stock > 0 ? `
                        <div class="product__quantity-controls">
                            <button class="quantity-btn" type="button" onclick="decreaseQuantity(this)">
                                <span class="material-icons">remove</span>
                            </button>
                            <input type="number" class="quantity-input" value="1" min="1" max="${producto.stock}" readonly>
                            <button class="quantity-btn" type="button" onclick="increaseQuantity(this)">
                                <span class="material-icons">add</span>
                            </button>
                        </div>
                        <button class="product__btn" onclick="addToCart(this)">
                            <span class="material-icons">shopping_cart</span>
                            Comprar
                        </button>
                    ` : `
                        <button class="product__btn product__btn--disabled" disabled>
                            <span class="material-icons">block</span>
                            Sin Stock
                        </button>
                    `}
                </div>
            </div>
        </article>
    `).join('');
    
    // Animar entrada
    animateProducts();
}


// FILTROS

function setupFilters() {
    const filterButtons = document.querySelectorAll('.filters__btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover clase activa
            filterButtons.forEach(btn => btn.classList.remove('filters__btn--active'));
            this.classList.add('filters__btn--active');
            
            // Aplicar filtro
            currentFilter = this.getAttribute('data-filter');
            applyFilter();
        });
    });
}

function applyFilter() {
    displayedCount = PRODUCTS_PER_PAGE; // Resetear contador
    
    if (currentFilter === 'todos') {
        filteredProducts = [...allProducts];
    } else {
        filteredProducts = allProducts.filter(producto => 
            producto.categoria_slug === currentFilter
        );
    }
    
    renderProducts();
    updateShowMoreButton();
}


// BOTÓN "MOSTRAR MÁS"

function setupShowMoreButton() {
    const showMoreBtn = document.getElementById('showMoreBtn');
    
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', () => {
            displayedCount += PRODUCTS_PER_PAGE;
            renderProducts();
            updateShowMoreButton();
        });
    }
}

function updateShowMoreButton() {
    const showMoreBtn = document.getElementById('showMoreBtn');
    
    if (!showMoreBtn) return;
    
    if (displayedCount >= filteredProducts.length) {
        showMoreBtn.style.display = 'none';
    } else {
        showMoreBtn.style.display = 'flex';
        
        const remaining = filteredProducts.length - displayedCount;
        const moreText = showMoreBtn.querySelector('.catalog__more-text');
        if (moreText) {
            moreText.textContent = `Mostrar más (${remaining} productos restantes)`;
        }
    }
}


// ESTADOS DE CARGA

function showLoading() {
    const grid = document.getElementById('productGrid');
    grid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 4rem 2rem;">
            <span class="material-icons rotating" style="font-size: 4rem; color: #23906F;">sync</span>
            <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">Cargando productos...</p>
        </div>
    `;
}

function showEmptyState() {
    const grid = document.getElementById('productGrid');
    grid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 4rem 2rem;">
            <span class="material-icons" style="font-size: 5rem; color: #ddd;">inventory_2</span>
            <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">
                No hay productos disponibles
            </p>
            <button onclick="loadProductsFromDB()" class="product__btn" style="margin-top: 1.5rem;">
                <span class="material-icons">refresh</span>
                Recargar
            </button>
        </div>
    `;
}

function showErrorState() {
    const grid = document.getElementById('productGrid');
    grid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 4rem 2rem;">
            <span class="material-icons" style="font-size: 5rem; color: #ff4444;">error</span>
            <p style="font-size: 1.2rem; color: #666; margin-top: 1rem;">
                Error al cargar productos
            </p>
            <button onclick="loadProductsFromDB()" class="product__btn" style="margin-top: 1.5rem;">
                <span class="material-icons">refresh</span>
                Reintentar
            </button>
        </div>
    `;
}


// ANIMACIÓN DE PRODUCTOS

function animateProducts() {
    const products = document.querySelectorAll('.product');
    
    products.forEach((product, index) => {
        product.style.opacity = '0';
        product.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            product.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            product.style.opacity = '1';
            product.style.transform = 'translateY(0)';
        }, index * 50);
    });
}


// FUNCIONES AUXILIARES PARA CANTIDADES

function increaseQuantity(button) {
    const input = button.closest('.product__quantity-controls').querySelector('.quantity-input');
    const max = parseInt(input.getAttribute('max')) || 999;
    let value = parseInt(input.value) || 1;
    
    if (value < max) {
        input.value = value + 1;
    } else {
        showToast('Stock máximo alcanzado', 'warning');
    }
}

function decreaseQuantity(button) {
    const input = button.closest('.product__quantity-controls').querySelector('.quantity-input');
    const min = parseInt(input.getAttribute('min')) || 1;
    let value = parseInt(input.value) || 1;
    
    if (value > min) {
        input.value = value - 1;
    }
}


// FUNCIÓN DE TOAST (debe estar definida o importada)

function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    
    const icon = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    }[type] || 'info';
    
    toast.innerHTML = `
        <span class="material-icons">${icon}</span>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

console.log('✅ Sistema de productos dinámicos cargado');
