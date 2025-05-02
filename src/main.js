// src/main.js

const base_url = CMP.base_url;
const API = url => `${base_url}/wp-json${url}`;

let labelTpl = ''
fetch('static/filter-label.html')
  .then(r => r.text())
  .then(t => labelTpl = t)

  

// Minimum required ACF fields
const requiredAcfFields = CMP.requiredAcfFields ;
const postType = CMP.post_type_name || 'producer';

if (!requiredAcfFields || Object.keys(requiredAcfFields).length === 0) {
  console.error("Failed to get ACF fields");
  throw new Error("Failed to get ACF fields");
}

// Build color lookup
const category_to_color = Object.fromEntries(
  (CMP.categories || []).map(c => [c.name, c.color])
);

// Setup popup renderer
let renderPopup;
const tpl = CMP.pin_template || '';
try {
  renderPopup = new Function('marker', `return \`${tpl}\``);
} catch (e) {
  console.error('Invalid pin template:', e);
  renderPopup = marker => `<b>${marker.title}</b><br><a href="${marker.page_url}">View</a>`;
}

async function fetch_image_url(id) {
  const res = await fetch(API(`/wp/v2/media/${id}?_fields=source_url`));
  if (!res.ok) throw new Error(res.statusText);
  return (await res.json()).source_url;
}

async function parseMarker(data) {
  if (!data.acf) throw new Error("Missing ACF block");

  const marker = { title: data.title?.rendered || '' , page_url : data.link};
  const requiredKeys = Object.values(requiredAcfFields);
  const missing = [];

  // Handle required fields
  for (const [markerKey, acfKey] of Object.entries(requiredAcfFields)) {
    if (markerKey === 'profile_img') {
      if (data.acf[acfKey]) {
        try { marker.profile_img_url = await fetch_image_url(data.acf[acfKey]); }
        catch { marker.profile_img_url = null; }
      } else {
        marker.profile_img_url = null;
        missing.push(acfKey);
      }
    } else {
      if (data.acf[acfKey] != null) {
        marker[markerKey] = (markerKey === 'latitude' || markerKey === 'longitude')
          ? Number(data.acf[acfKey])
          : data.acf[acfKey];
      } else {
        throw new Error(`Required field ${acfKey} missing in post ${data.id}`);
      }
    }
  }

  // Always fallback for page_url if page_url ACF field is missing
  if (!marker.page_url) {
    marker.page_url = data.link;
  }

  // Pass through ALL other ACF fields
  for (const [acfKey, value] of Object.entries(data.acf)) {
    if (!requiredKeys.includes(acfKey)) {
      marker[acfKey] = value;
    }
  }

  if (missing.length) console.warn(`Optional fields missing in ${data.id}:`, missing);

  return marker;
}

// Map setup
const map = L.map('map').setView([9.9, -84.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const categoryLayers = {};
const filterMenu = document.getElementById('category-filters');

fetch(API(`/wp/v2/${postType}?per_page=100`))
  .then(r => r.json())
  .then(async data => {
    const markers = await Promise.all(data.map(d => parseMarker(d).catch(e => {
      console.error('Invalid marker:', e);
      return null;
    })));
    markers.filter(m => m).forEach(marker => {
      const cat = marker.category || 'Others';
      const color = category_to_color[cat] || '#000000';

      const icon = L.ExtraMarkers.icon({
        shape: 'circle', markerColor: color, prefix: 'fa', icon: 'fa-map-marker', svg: true
      });

      const popupHtml = renderPopup(marker);

      if (!categoryLayers[cat]) {
        
        filterMenu.append(generate_category_box(cat, color));

      }
      

      L.marker([marker.latitude, marker.longitude], { icon })
        .bindPopup(popupHtml)
        .addTo(categoryLayers[marker.category]);
    });
    if (!filterMenu.querySelector('#filter-all')) {
      filterMenu.append(generate_category_box("Show all", "#000000"));
    }

    const allCb = filterMenu.querySelectorAll('input');
    const filterAllCheckbox = allCb[allCb.length - 1];
    filterAllCheckbox.onchange = () => handleFilterAllChange(filterAllCheckbox);
  })
  .catch(err => console.error('Error loading producers:', err));

  function generate_category_box(cat, color) {
    categoryLayers[cat] = L.layerGroup().addTo(map);
    const html = labelTpl
      .replace(/{{category}}/g, cat)
      .replace(/{{color}}/g, color);
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const btn = wrapper.querySelector('.category-btn');
  
    if (cat === 'Show all') {
      // toggle every layer
      btn.addEventListener('click', () => {
        // if any layer is hidden, show them all, else hide them all
        const anyHidden = Object.values(categoryLayers)
          .some(layer => !map.hasLayer(layer));
        Object.values(categoryLayers).forEach(layer => {
          anyHidden ? map.addLayer(layer) : map.removeLayer(layer);
        });
        // update other buttons’ active state
        document
          .querySelectorAll('.category-btn')
          .forEach(b => b.classList.toggle('inactive', !anyHidden));
      });
    } else {
      // per‐category toggle
      btn.addEventListener('click', () => {
        const active = !btn.classList.toggle('inactive');
        active
          ? map.addLayer(categoryLayers[cat])
          : map.removeLayer(categoryLayers[cat]);
      });
    }
  
    return wrapper.firstElementChild;
  }
  
  

// Filter logic
function handleFilterChange(cb) {
  const layer = categoryLayers[cb.value];
  cb.checked ? map.addLayer(layer) : map.removeLayer(layer);
  updateShowAllState();
}

function handleFilterAllChange(cb) {
  const cbs = Array.from(filterMenu.querySelectorAll('input'));
  const categoryCbs = cbs.slice(0, -1);
  categoryCbs.forEach(ck => {
    ck.checked = cb.checked;
    ck.checked
      ? map.addLayer(categoryLayers[ck.value])
      : map.removeLayer(categoryLayers[ck.value]);
  });
}

function updateShowAllState() {
  const cbs = Array.from(filterMenu.querySelectorAll('input'));
  const categoryCbs = cbs.slice(0, -1);
  const all = categoryCbs.every(cb => cb.checked);
  const none = categoryCbs.every(cb => !cb.checked);
  const filterAllCheckbox = cbs[cbs.length - 1];
  filterAllCheckbox.checked = all;
  filterAllCheckbox.indeterminate = !all && !none;
}

