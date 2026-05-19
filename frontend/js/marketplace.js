/**
 * UniLink — marketplace.js
 */
let mktPage = 1, mktHasMore = true, mktLoading = false;
let activeCategory = '';

document.addEventListener('DOMContentLoaded', () => {
  loadListings(true);
  setupChips();
  setupListingForm();
  setupImagePreview();
  setupInfiniteScroll();
});

/* ---- Load listings ---- */
async function loadListings(reset = false) {
  if (mktLoading || (!mktHasMore && !reset)) return;
  if (reset) {
    mktPage = 1; mktHasMore = true;
    document.getElementById('listings-grid').innerHTML =
      Array(8).fill('<div class="listing-skeleton"></div>').join('');
    document.getElementById('listings-end').classList.add('hidden');
  }
  mktLoading = true;
  document.getElementById('listings-loader').classList.remove('hidden');

  const q       = document.getElementById('mkt-search').value;
  const sort    = document.getElementById('mkt-sort').value;
  const priceMin = document.getElementById('price-min').value;
  const priceMax = document.getElementById('price-max').value;

  const params = new URLSearchParams({
    page: mktPage, limit: 12,
    ...(activeCategory && { category: activeCategory }),
    ...(q && { q }),
    sort,
    ...(priceMin && { price_min: priceMin }),
    ...(priceMax && { price_max: priceMax }),
  });

  try {
    const res = await apiFetch(`marketplace/listings?${params}`);
    const { data: listings, meta } = res;

    if (reset) document.getElementById('listings-grid').innerHTML = '';

    if (!listings.length && mktPage === 1) {
      document.getElementById('listings-grid').innerHTML =
        `<div class="card card-body" style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text-muted)">
           <p style="font-size:32px">🛒</p>
           <p style="font-size:16px;font-weight:600;margin-top:12px">Sin anuncios disponibles</p>
           <p style="font-size:14px;margin-top:6px">¡Sé el primero en publicar algo!</p>
         </div>`;
    } else {
      listings.forEach(l => {
        document.getElementById('listings-grid').appendChild(renderListing(l));
      });
    }

    mktHasMore = meta.has_more;
    mktPage++;
    if (!mktHasMore) document.getElementById('listings-end').classList.remove('hidden');
  } catch (err) {
    showToast('Error al cargar anuncios', 'error');
  } finally {
    mktLoading = false;
    document.getElementById('listings-loader').classList.add('hidden');
  }
}

/* ---- Render listing card ---- */
function renderListing(l) {
  const el = document.createElement('div');
  el.className = 'listing-card';
  el.onclick = () => openListingDetail(l.listing_id);

  const catIcons = { libros:'📚', calculadoras:'🔢', tutorias:'👨‍🏫', electronica:'💻', ropa:'👕', otros:'📦' };
  const condLabels = { nuevo:'✨ Nuevo', como_nuevo:'🌟 Como nuevo', buen_estado:'👍 Buen estado', usado:'📦 Usado' };
  const stars = '⭐'.repeat(Math.round(l.seller_rating || 0));

  el.innerHTML = `
    <div class="listing-thumb">
      ${l.thumbnail
        ? `<img src="${escHtml(l.thumbnail)}" alt="${escHtml(l.title)}" loading="lazy">`
        : catIcons[l.category] || '📦'}
    </div>
    <div class="listing-body">
      <div class="listing-category">${catIcons[l.category] || ''} ${escHtml(l.category)}</div>
      <div class="listing-title">${escHtml(l.title)}</div>
      <div class="listing-price">$${parseFloat(l.price).toFixed(2)}</div>
      <div class="listing-meta">
        <div class="listing-seller">
          <div class="avatar avatar-sm">${(l.seller_name||'?')[0].toUpperCase()}</div>
          <span>${escHtml(l.seller_name || '')}</span>
          ${l.seller_rating ? `<span class="seller-rep">${stars}</span>` : ''}
        </div>
        <span class="listing-condition">${condLabels[l.condition_val] || l.condition_val}</span>
      </div>
    </div>`;
  return el;
}

/* ---- Open detail modal ---- */
async function openListingDetail(id) {
  document.getElementById('listingDetailModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  document.getElementById('listing-detail-body').innerHTML =
    '<div style="grid-column:1/-1;text-align:center;padding:40px"><div class="spinner" style="border-top-color:var(--uni-blue-mid);margin:0 auto"></div></div>';

  try {
    const res = await apiFetch(`marketplace/listings/${id}`);
    const l = res.listing || res.data?.listing || res.data;
    document.getElementById('detail-title').textContent = l.title;

    const images = l.images?.length ? l.images : [null];
    const mainImg = images[0]?.url || null;
    const thumbsHtml = images.length > 1
      ? `<div class="detail-thumbs">${images.map((img, i) =>
          `<img src="${escHtml(img.url)}" class="detail-thumb${i===0?' active':''}"
               onclick="switchDetailImg(this, '${escHtml(img.url)}')" alt="">`
        ).join('')}</div>` : '';

    const stars = '⭐'.repeat(Math.round(l.seller_rating || 0));
    const isOwner = l.seller_id === UL_USER.id;

    document.getElementById('listing-detail-body').innerHTML = `
      <div class="detail-images">
        <div class="detail-main-img" id="detail-main-img">
          ${mainImg ? `<img src="${escHtml(mainImg)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius-lg)">` : '<div style="height:280px;display:flex;align-items:center;justify-content:center;font-size:64px;background:var(--gray-100);border-radius:var(--radius-lg)">📦</div>'}
        </div>
        ${thumbsHtml}
      </div>

      <div class="detail-info">
        <div>
          <div class="listing-category" style="font-size:12px">${escHtml(l.category)}</div>
          <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;margin:6px 0">${escHtml(l.title)}</h3>
          <div class="detail-price">$${parseFloat(l.price).toFixed(2)} MXN</div>
          <span class="badge badge-gray">${escHtml(l.condition_val?.replace('_', ' ') || '')}</span>
          ${l.allow_offers ? '<span class="badge badge-green" style="margin-left:6px">Acepta ofertas</span>' : ''}
        </div>

        ${l.description ? `<p class="detail-desc">${escHtml(l.description)}</p>` : ''}

        <div class="detail-seller-card">
          <div class="avatar">${(l.seller_name||'?')[0].toUpperCase()}</div>
          <div class="detail-seller-info">
            <div class="detail-seller-name">${escHtml(l.seller_name || '')}</div>
            <div class="detail-seller-rep">${stars || 'Sin calificaciones aún'} (${l.reviews_count || 0} reseñas)</div>
          </div>
          <button class="btn-ghost" onclick="viewProfile(${l.seller_id})">Ver perfil</button>
        </div>

        <div class="detail-actions">
          ${isOwner
            ? `<button class="btn-secondary" onclick="editListing(${l.listing_id})">✏️ Editar</button>
               <button class="btn-danger" onclick="deleteListing(${l.listing_id})">🗑 Eliminar</button>`
            : `<button class="btn-primary" onclick="contactSeller(${l.seller_id}, ${l.listing_id})">💬 Contactar vendedor</button>
               ${l.allow_offers ? `<button class="btn-secondary" onclick="makeOffer(${l.listing_id})">💰 Hacer oferta</button>` : ''}`
          }
        </div>

        <div class="reviews-section">
          <h4 style="font-size:14px;font-weight:600;margin-bottom:12px">Reseñas del vendedor</h4>
          <div id="reviews-list">
            ${(l.reviews||[]).length
              ? l.reviews.map(r => `
                  <div class="review-item">
                    <div class="avatar avatar-sm">${(r.reviewer_name||'?')[0].toUpperCase()}</div>
                    <div>
                      <div style="font-size:13px;font-weight:600">${escHtml(r.reviewer_name||'')}</div>
                      <div class="review-stars">${'⭐'.repeat(r.rating)}</div>
                      ${r.comment ? `<div class="review-text">${escHtml(r.comment)}</div>` : ''}
                    </div>
                  </div>`).join('')
              : '<p style="font-size:13px;color:var(--text-muted)">Sin reseñas aún.</p>'
            }
          </div>
        </div>
      </div>`;
  } catch {
    showToast('Error al cargar el anuncio', 'error');
  }
}

function switchDetailImg(thumb, url) {
  document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
  const main = document.getElementById('detail-main-img');
  main.innerHTML = `<img src="${escHtml(url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius-lg)">`;
}

/* ---- Create listing ---- */
function openNewListingModal() {
  document.getElementById('newListingModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function setupListingForm() {
  document.getElementById('listing-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Publicando...';

    const fd = new FormData(e.target);
    try {
      const res = await fetch(`${UL_BASE}/backend/api-gateway/index.php?service=marketplace&path=marketplace/listings`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${UL_TOKEN}` },
        body: fd
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      closeModal('newListingModal');
      showToast('¡Anuncio publicado! 🎉', 'success');
      loadListings(true);
    } catch (err) {
      showToast(err.message || 'Error al publicar', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Publicar anuncio';
    }
  });
}

function setupImagePreview() {
  document.getElementById('listing-images')?.addEventListener('change', function () {
    const preview = document.getElementById('listing-img-preview');
    preview.innerHTML = '';
    [...this.files].slice(0, 4).forEach(file => {
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'media-preview-item';
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });
}

/* ---- Filters ---- */
function setupChips() {
  document.querySelectorAll('#category-chips .chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('#category-chips .chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      activeCategory = chip.dataset.cat;
      loadListings(true);
    });
  });
}

let filterTimeout;
function filterListings() {
  clearTimeout(filterTimeout);
  filterTimeout = setTimeout(() => loadListings(true), 400);
}

function setupInfiniteScroll() {
  const obs = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting && mktHasMore && !mktLoading) loadListings();
  }, { threshold: 0.5 });
  const el = document.getElementById('listings-loader');
  if (el) obs.observe(el);
}

/* ---- Actions ---- */
async function contactSeller(sellerId, listingId) {
  showToast('Función de mensajería próximamente 💬', 'info');
}

async function makeOffer(listingId) {
  const amount = prompt('¿Cuánto ofreces? (MXN)');
  if (!amount || isNaN(amount)) return;
  try {
    await apiFetch(`marketplace/listings/${listingId}/offer`, {
      method: 'POST',
      body: JSON.stringify({ amount: parseFloat(amount) })
    });
    showToast('¡Oferta enviada al vendedor!', 'success');
  } catch { showToast('Error al enviar oferta', 'error'); }
}

async function deleteListing(id) {
  if (!confirm('¿Eliminar este anuncio?')) return;
  try {
    await apiFetch(`marketplace/listings/${id}`, { method: 'DELETE' });
    closeModal('listingDetailModal');
    showToast('Anuncio eliminado', 'success');
    loadListings(true);
  } catch { showToast('Error al eliminar', 'error'); }
}

function viewProfile(userId) {
  window.location.href = `profile.php?id=${userId}`;
}

function closeModal(id) {
  document.getElementById(id).classList.add('hidden');
  document.body.style.overflow = '';
}
