// import L from 'leaflet'
// import 'leaflet-extra-markers'; // Ensure this is imported if you're using L.ExtraMarkers

// Keep your category definitions

const base_url = CMP.base_url;
const API = url=> `${base_url}/wp-json${url}`;


const category_to_color = {
  Accommodation: "#FF5733",
  Restaurants: "#33FF57",
  Cultural: "#3357FF",
  Stores: "#FF33A1",
  Nature: "#33FFF5",
  Others: "#FFC300",
};

async function fetch_image_url(media_id) {
  try {
    const response = await fetch(
      API(`/wp/v2/media/${media_id}?_fields=source_url`)
    );

    if (!response.ok) {
      console.error("Error fetching image URL:", response.statusText);
      throw new Error(response.statusText);
    }

    const media = await response.json();
    return media.source_url;
  } catch (error) {
    console.error("Error fetching image URL:", error);
    return null; // Return null or some default value if fetching fails
  }
}

async function parseMarkerFromApi(data) {
  if (!data.acf || !data.acf.latitude || !data.acf.longitude) {
    throw new Error("Invalid data: missing required fields");
  }

  try {
    const img_url = await fetch_image_url(data.acf.profile_img);
    return {
      title: data.title?.rendered || "",
      category: data.acf.category || "",
      page_url: data.link || "",
      profile_img_url: img_url,
      latitude: Number(data.acf.latitude),
      longitude: Number(data.acf.longitude),
    };
  } catch (e) {
    console.error("Error processing image URL:", e);
    return {
      title: data.title?.rendered || "",
      category: data.acf.category || "",
      page_url: data.link || "",
      profile_img_url: null, // Set to null or a default value in case of an error
      latitude: Number(data.acf.latitude),
      longitude: Number(data.acf.longitude),
    };
  }
}

const map = L.map("map").setView([9.9, -84.0], 8);

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: "&copy; OpenStreetMap contributors",
}).addTo(map);

// Object to hold layer groups for each category
const categoryLayers = {};


// Get references to the menu container
const filterMenu = document.getElementById('category-filters');
const filterAllCheckbox = document.getElementById('filter-all');

// Fetch data and create markers/layers
fetch(API('/wp/v2/producer?per_page=100'))
  .then((res) => res.json())
  .then(async (data) => {
    const promises = data.map(async (item) => {
      try {
        return await parseMarkerFromApi(item);
      } catch (e) {
        console.error("Invalid marker:", e);
        return null; // Return null for invalid markers
      }
    });

    const resolvedMarkers = (await Promise.all(promises)).filter(marker => marker !== null);

    resolvedMarkers.forEach((marker) => {
      const category = marker.category || "Others"; // Ensure category exists
      const color = category_to_color[category] || category_to_color["Others"]; // Default color

      // Create the marker icon
      const colored_marker = L.ExtraMarkers.icon({
        shape: 'circle',
        markerColor: color,
        prefix: 'fa',
        icon: 'fa-map-marker',
        iconColor: '#fff',
        iconRotate: 0,
        extraClasses: '',
        number: '',
        svg: true,
      });

      // Create the Leaflet marker
      const leaflet_marker = L.marker([marker.latitude, marker.longitude], {
         icon: colored_marker,
         // Store category data within the marker options for easier access later if needed
         category: category
      }).bindPopup(
        `<b>${marker.title}</b></br> ${marker.profile_img_url ? `<img src="${marker.profile_img_url}" alt="${marker.title}" width="200"><br>` : ''}<a href="${marker.page_url}">View</a>`
      );

      // --- Layer Group Logic ---
      // If this category doesn't have a layer group yet, create it
      if (!categoryLayers[category]) {
        categoryLayers[category] = L.layerGroup();
        // Add the new group to the map by default (optional, depends on initial view)
        categoryLayers[category].addTo(map);

        // --- Create Filter UI Element Dynamically ---
        const div = document.createElement('div');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = `filter-${category.replace(/\s+/g, '-')}`; // Create a safe ID
        checkbox.value = category;
        checkbox.checked = true; // Start checked
        checkbox.addEventListener('change', handleFilterChange); // Add listener

        const label = document.createElement('label');
        label.htmlFor = checkbox.id;
        // Optional: Add a color swatch
        label.innerHTML = `<span style="display:inline-block; width:12px; height:12px; background-color:${color}; margin-right: 5px; border-radius: 50%;"></span>${category}`;


        div.appendChild(checkbox);
        div.appendChild(label);
        filterMenu.appendChild(div);
      }

      // Add the marker to its category's layer group
      leaflet_marker.addTo(categoryLayers[category]);
    });

    // Add listener for the "Show All" checkbox
    filterAllCheckbox.addEventListener('change', handleFilterAllChange);

  })
  .catch(error => {
    console.error("Error fetching or processing producer data:", error);
  });


// --- Filter Logic Functions ---

function handleFilterChange(event) {
  const checkbox = event.target;
  const category = checkbox.value;
  const layerGroup = categoryLayers[category];

  if (checkbox.checked && layerGroup) {
    map.addLayer(layerGroup);
  } else if (!checkbox.checked && layerGroup) {
    map.removeLayer(layerGroup);
  }

  // Update "Show All" checkbox state
  updateShowAllCheckboxState();
}

function handleFilterAllChange(event) {
    const isChecked = event.target.checked;
    const categoryCheckboxes = filterMenu.querySelectorAll('input[type="checkbox"]');

    categoryCheckboxes.forEach(cb => {
        cb.checked = isChecked;
        // Manually trigger the filter change for each category
        const category = cb.value;
        const layerGroup = categoryLayers[category];
        if(layerGroup) {
            if (isChecked) {
                map.addLayer(layerGroup);
            } else {
                map.removeLayer(layerGroup);
            }
        }
    });
}

function updateShowAllCheckboxState() {
    const categoryCheckboxes = filterMenu.querySelectorAll('input[type="checkbox"]');
    let allChecked = true;
    let noneChecked = true;

    categoryCheckboxes.forEach(cb => {
        if (cb.checked) {
            noneChecked = false;
        } else {
            allChecked = false;
        }
    });

    if (allChecked) {
        filterAllCheckbox.checked = true;
        filterAllCheckbox.indeterminate = false;
    } else if (noneChecked) {
        filterAllCheckbox.checked = false;
        filterAllCheckbox.indeterminate = false;
    } else {
        // Some are checked, some are not
        filterAllCheckbox.checked = false;
        filterAllCheckbox.indeterminate = true; // Visually indicates partial selection
    }
}