// src/main.js

const base_url = CMP.base_url;
const API = url => `${base_url}/wp-json${url}`;

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
const filterAllCheckbox = document.getElementById('filter-all');

fetch(API(`/wp/v2/${postType}?per_page=100`))
  .then(r => r.json())
  .then(async data => {
    const markers = await Promise.all(data.map(d => parseMarker(d).catch(e => {
      console.error('Invalid marker:', e);
      return null;
    })));
    markers.filter(m => m).forEach(marker => {
      const cat = marker.category || 'Others';
      const color = category_to_color[cat] || '#888';

      const icon = L.ExtraMarkers.icon({
        shape: 'circle', markerColor: color, prefix: 'fa', icon: 'fa-map-marker', svg: true
      });

      const popupHtml = renderPopup(marker);

      if (!categoryLayers[cat]) {
        categoryLayers[cat] = L.layerGroup().addTo(map);
        const div = document.createElement('div');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.id = `filter-${cat}`;
        cb.value = cat;
        cb.checked = true;
        cb.onchange = () => handleFilterChange(cb);
        const label = document.createElement('label');
        label.htmlFor = cb.id;
        label.innerHTML = `<span style="width:12px;height:12px;display:inline-block;background:${color};border-radius:50%;margin-right:5px"></span>${cat}`;
        div.append(cb, label);
        filterMenu.append(div);
      }

      L.marker([marker.latitude, marker.longitude], { icon })
        .bindPopup(popupHtml)
        .addTo(categoryLayers[marker.category]);
    });

    filterAllCheckbox.onchange = () => handleFilterAllChange(filterAllCheckbox);
  })
  .catch(err => console.error('Error loading producers:', err));

// Filter logic
function handleFilterChange(cb) {
  const layer = categoryLayers[cb.value];
  cb.checked ? map.addLayer(layer) : map.removeLayer(layer);
  updateShowAllState();
}

function handleFilterAllChange(cb) {
  Object.values(categoryLayers).forEach((layer, i) => {
    const ck = filterMenu.querySelectorAll('input')[i];
    ck.checked = cb.checked;
    cb.checked ? map.addLayer(layer) : map.removeLayer(layer);
  });
}

function updateShowAllState() {
  const cbs = filterMenu.querySelectorAll('input');
  const all = Array.from(cbs).every(cb => cb.checked);
  const none = Array.from(cbs).every(cb => !cb.checked);
  filterAllCheckbox.checked = all;
  filterAllCheckbox.indeterminate = !all && !none;
}
