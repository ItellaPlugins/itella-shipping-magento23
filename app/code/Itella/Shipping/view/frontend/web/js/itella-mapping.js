class itellaMapping {
  constructor(el) {

    /* Itella Mapping version */
    this.version = '1.0.3';

    this._isDebug = false;

    /* default map center Lithuania Kaunas */
    this._defaultMapPos = [54.890926, 23.919338];

    /* zoom levels for map */
    this.ZOOM_DEFAULT = 8;
    this.ZOOM_SELECTED = 13;

    /**
     * Element where to mount Itella Mapping
     * @type HTMLElement
     */
    this.mount = el;

    /* Leaflet elements */
    this._map = null;
    this._pickupIcon = null;
    this._markerLayer = null;
    this._latlongArray = [];

    /* Pointers to often used modal elements */
    this.UI = {};
    this.UI.container = null;
    this.UI.modal = null;

    this.images_url = '';

    this.strings = {
      modal_header: 'Pickup points',
      selector_header: 'Pickup point',
      workhours_header: 'Workhours',
      contacts_header: 'Contacts',
      search_placeholder: 'Enter postcode/address',
      select_pickup_point: 'Select a pickup point',
      no_pickup_points: 'No points to select',
      select_btn: 'select',
      back_to_list_btn: 'reset search',
      nothing_found: 'Nothing found',
      select_pickup_point_btn: 'Select pickup point',
      no_information: 'No information',
      error_leaflet: 'Leaflet is required for Itella-Mapping',
      error_missing_mount_el: 'No mount supplied to itellaShipping'
    }

    this.country = 'LT';

    /* Functions to run after point is selected */
    this.callbackList = [];

    /* Selected pickup */
    this.selectedPoint = null;
    this.locations = [];
    this._locations = [];
    this._isSearchResult = false;

    this._searchTimeoutId = null;

    if (typeof L === 'undefined') {
      console.error(this.strings.error_leaflet);
    }
  }

  setImagesUrl(images_url) {
    this.images_url = images_url;
    return this;
  }

  setStrings(strings) {
    this.strings = { ...this.strings, ...strings };
    return this;
  }

  getStrings() {
    return this.strings;
  }

  init() {
    if (typeof this.mount !== 'undefined') {
      this.buildContainer()
        .buildModal()
        .setupLeafletMap(this.UI.modal.getElementsByClassName('itella-map')[0])
        .attachListeners();
      return this;
    }
    return false;
  }

  getVersion() {
    return this.version;
  }

  getMount() {
    return this.mount;
  }

  showEl(el) {
    el.classList.remove('hidden');
  }

  hideEl(el) {
    el.classList.add('hidden');
  }

  buildModal() {
    var template =
      `
    <div class="itella-container">
      <div class="close-modal">
        <img src="${this.images_url}x-symbol.svg" alt="Close map">
      </div>
      <div class="itella-map"></div>
      <div class="itella-card">
        <div class="itella-card-header">
          <h2>${this.strings.modal_header}</h2>
          <img src="${this.images_url}logo_small_white.png" alt="Itella logo">
        </div>
        <div class="itella-card-content">
          <h3>${this.strings.selector_header}</h3>
          <div class="itella-select">
            <div class="dropdown">${this.strings.select_pickup_point}</div>
            <div class="dropdown-inner">
              <div class="search-bar">
                <input type="text" placeholder="${this.strings.search_placeholder}" class="search-input">
                <img src="${this.images_url}search.png" alt="Search">
              </div>
              <span class="search-by"></span>
              <ul>
                <li class="city">${this.strings.no_pickup_points}</li>
              </ul>
            </div>
          </div>

          <div class="point-info">
            <div class="workhours">
              <h4 class="title">${this.strings.workhours_header}</h4>
              <div class="workhours-info">
                <ol>
                </ol>
              </div>
            </div>
            <div class="contacts">
              <h4 class="title">${this.strings.contacts_header}</h4>
              <div class="contacts-info">
                <ul>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="itella-card-footer">
          <button class="itella-btn itella-back hidden">${this.strings.back_to_list_btn}</button>
          <button class="itella-btn itella-submit">${this.strings.select_btn}</button>
        </div>
      </div>
    </div>
    `;
    if (typeof this.mount === 'undefined') {
      console.info(this.strings.error_missing_mount_el);
      return false;
    }
    let modal = this.createElement('div', ['itella-mapping-modal', 'hidden']);
    modal.innerHTML = template;

    /* if exists destroy and rebuild */
    if (this.UI.modal !== null) {
      this.UI.modal.parentNode.removeChild(this.UI.modal);
      this.UI.modal = null;
    }

    this.UI.modal = modal;
    this.UI.container.appendChild(this.UI.modal);
    this.UI.back_to_list_btn = this.UI.container.querySelector('.itella-btn.itella-back');

    return this;
  }

  buildContainer() {
    let template = `
      <div class="itella-chosen-point">${this.strings.select_pickup_point}</div>
      <a href='#' class="itella-modal-btn">${this.strings.select_pickup_point_btn}</a>
    `;
    let container = this.createElement('div', ['itella-shipping-container']);
    container.innerHTML = template;

    this.UI.container = container;
    this.mount.appendChild(this.UI.container);

    return this;
  }

  attachListeners() {
    let _this = this;
    this.UI.container.getElementsByClassName('itella-modal-btn')[0].addEventListener('click', function (e) {
      e.preventDefault();
      _this.showEl(_this.UI.modal);
      _this._map.invalidateSize();
      if (_this.selectedPoint === null) {
        _this.setMapView(_this._defaultMapPos, _this.ZOOM_DEFAULT);
      } else {
        _this.setMapView(_this.selectedPoint.location, _this.ZOOM_SELECTED);
        if (typeof _this.selectedPoint._marker._icon !== 'undefined') {
          _this.selectedPoint._marker._icon.classList.add('active');
        }
      }
    });

    this.UI.container.getElementsByClassName('search-input')[0].addEventListener('keyup', function (e) {
      e.preventDefault();
      let force = false;
      /* Enter key forces search to not wait */
      if (e.keyCode == '13') {
        force = true;
      }
      _this.searchNearestDebounce(this.value, force);
    });

    this.UI.container.getElementsByClassName('close-modal')[0].addEventListener('click', function (e) {
      e.preventDefault();
      _this.hideEl(_this.UI.modal);
    });

    this.UI.container.getElementsByClassName('itella-submit')[0].addEventListener('click', function (e) {
      e.preventDefault();
      _this.submitSelection();
    });

    let select = _this.UI.modal.querySelector('.itella-select');
    let drpd = _this.UI.modal.querySelector('.itella-select .dropdown');
    let select_options = select.querySelector('.dropdown-inner');
    drpd.addEventListener('click', function (e) {
      e.preventDefault();
      select.classList.toggle('open');
    });
    this.UI.modal.addEventListener('click', function (e) {
      if (select.classList.contains('open')
        && !(select_options.contains(e.target) || drpd == e.target)
      ) {
        select.classList.remove('open');
      }
      if (_this._isDebug) {
        console.log('CLICKED HTML EL:', e.target.nodeName, e.target.dataset);
      }
      if (e.target.nodeName.toLowerCase() == 'li' && typeof e.target.dataset.id !== 'undefined') {
        let point = _this.getLocationById(e.target.dataset.id);
        if (_this._isDebug) {
          console.log('Selected from dropdown:', point);
        }
        _this.selectedPoint = point;
        _this.renderPointInfo(point);
        select.classList.remove('open');
        _this.setMapView(point.location, _this.ZOOM_SELECTED);
        _this.setActiveMarkerByTerminalId(e.target.dataset.id);
      }
    });

    this._markerLayer.on('click', function (e) {
      _this._markerLayer.eachLayer(function (icon) {
        L.DomUtil.removeClass(icon._icon, "active");
      });
      L.DomUtil.addClass(e.layer._icon, "active");
      _this.setMapView(e.layer.getLatLng(), _this._map.getZoom());
      let temp = _this.getLocationById(e.layer.options.pickupPointId);
      _this.renderPointInfo(temp);
      _this.selectedPoint = temp;
      if (_this._isDebug) {
        console.log('Selected pickup point ID:', temp, e.layer.getLatLng());
      }
    });

    return this;
  }

  resetSearch() {
    this._isSearchResult = false;
    this.locations.forEach(function (loc) {
      loc.distance = undefined;
    });
    this.locations.sort(this.sortByCity);
    this.updateDropdown();
    this.UI.modal.querySelector('.search-by').innerText = '';
    this.UI.container.getElementsByClassName('search-input')[0].value = '';
  }

  searchNearestDebounce(search, force) {
    let _this = this;
    clearTimeout(this._searchTimeoutId);
    /* if enter is pressed no need to wait */
    if (force) {
      this.searchNearest(search);
      return;
    }
    this._searchTimeoutId = setTimeout(this.searchNearest.bind(_this), 1000, search);
  }

  searchNearest(search) {
    let _this = this;
    clearTimeout(_this._searchTimeoutId);
    /* reset dropdown if search is empty */
    if (!search.length) {
      this.resetSearch();
    }

    let oReq = new XMLHttpRequest();
    /* access itella class inside response handler */
    oReq.itella = this;
    oReq.addEventListener('loadend', this._handleResponse);
    oReq.open('GET', "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?singleLine=" + search + "&sourceCountry=" + this.country + "&category=&outFields=Postal&maxLocations=1&forStorage=false&f=pjson");
    oReq.send();
  }

  _handleResponse() {
    let _this = this.itella;
    let search_by = _this.UI.modal.querySelector('.search-by');
    if (this.status != 200) {
      search_by.innerText = _this.strings.nothing_found;
      return false;
    }

    let json = JSON.parse(this.responseText);

    if (_this._isDebug) {
      console.log('GEOCODE RESPONSE:', json);
    }
    if (json.candidates != undefined && json.candidates.length > 0) {
      _this._isSearchResult = true;
      search_by.innerText = json.candidates[0].address;
      _this.addDistance({ lat: json.candidates[0].location.y, lon: json.candidates[0].location.x });
      return true;
    }

    search_by.innerText = _this.strings.nothing_found;
    return false;
  }

  addDistance(origin) {
    let _this = this;
    this.locations.forEach(function (loc) {
      loc.distance = _this.calculateDistance(origin, loc.location);
    });
    this.locations.sort(this.sortByDistance);
    this.updateDropdown();
  }

  deg2rad(degress) {
    return degress * Math.PI / 180;
  }

  rad2deg(radians) {
    return radians * 180 / Math.PI;
  }

  calculateDistance(loc1, loc2) {
    let distance = NaN;
    if ((loc1.lat == loc2.$lat) && (loc.lon == loc2.lon)) {
      return 0;
    } else {
      let theta = loc1.lon - loc2.lon;
      let dist = Math.sin(this.deg2rad(loc1.lat)) * Math.sin(this.deg2rad(loc2.lat))
        + Math.cos(this.deg2rad(loc1.lat)) * Math.cos(this.deg2rad(loc2.lat)) * Math.cos(this.deg2rad(theta));
      dist = Math.acos(dist);
      dist = this.rad2deg(dist);
      distance = dist * 60 * 1.1515 * 1.609344;
    }
    return distance;
  }

  registerCallback(callback) {
    if (typeof callback !== 'function') {
      return false;
    }

    return this.callbackList.push(callback);
  }

  /**
   * To work with IE11 we cant use array.find() so this replaces it for our simple usecase
   * @param {Function} checkFn 
   * @param {Array} array 
   */
  findLocInArray(checkFn, array) {
    for (var i = 0; i < array.length; i++) {
      if (checkFn(array[i])) {
        return array[i];
      }
    }
    return undefined;
  }

  setSelection(id, manual = false) {
    let location = this.getLocationByPupCode(id);
    if (!location) { // try looking by ID
      location = this.getLocationById(id);
    }

    if (typeof location !== 'undefined') {
      this.selectedPoint = location;
      this.renderPointInfo(location);
      this.setActiveMarkerByTerminalId(location.id);
      this.setMapView(location.location, this.ZOOM_SELECTED);
      this.submitSelection(manual);
    }
  }

  submitSelection(manual = true) {
    /* make sure there is something selected */
    if (this.selectedPoint == null) {
      return false;
    }
    let _this = this;
    if (this.callbackList.length > 0) {
      this.callbackList.forEach(function (callback) {
        callback.call(_this, manual);
      });
    }
    let selectedEl = this.UI.container.getElementsByClassName('itella-chosen-point')[0];
    selectedEl.innerText = this.selectedPoint.publicName;

    this.hideEl(this.UI.modal);
  }

  renderPointInfo(location) {
    this.locations.forEach(loc => loc._li.classList.remove('active'));
    location._li.classList.add('active');

    let pointInfo = this.UI.modal.querySelector('.point-info');
    let workhours = pointInfo.querySelector('.workhours ol');
    let contacts = pointInfo.querySelector('.contacts ul');

    let openingTimes = [];
    location.openingTimes.forEach(function (time) {
      openingTimes[time.weekday] = { from: time.timeFrom, to: time.timeTo };
    });
    let openHTML = '<div>' + this.strings.no_information + '</div>';
    if (openingTimes.length) {
      openHTML = openingTimes.map(function (time) {
        return `<li>${time.from} - ${time.to}</li>`;
      }).join('\n');
    }
    workhours.innerHTML = openHTML;

    let contactHTML = '<div>' + this.strings.no_information + '</div>';
    contactHTML = `
      <li>${location.address.streetName} ${location.address.streetNumber},</li>
      <li>${location.address.municipality} ${location.address.postalCode}</li>
    `;
    if (location.locationName !== null) {
      contactHTML += `<li>${location.locationName}</li>`;
    }
    if (location.customerServicePhoneNumber !== null) {
      contactHTML += `<li>${location.customerServicePhoneNumber}</li>`;
    }
    if (location.additionalInfo !== null) {
      contactHTML += `<li>${location.additionalInfo}</li>`;
    }
    contacts.innerHTML = contactHTML;

    var drpd = this.UI.modal.querySelector('.itella-select .dropdown');
    drpd.innerText = location.publicName;

    return this;
  }

  setActiveMarkerByTerminalId(id) {
    this._markerLayer.eachLayer(function (icon) {
      if (typeof icon._icon !== 'undefined') {
        L.DomUtil.removeClass(icon._icon, "active");
        if (icon.options.pickupPointId === id) {
          L.DomUtil.addClass(icon._icon, "active");
        }
      }
    });
  }

  getLocationById(id) {
    return this.findLocInArray(function (loc) {
      return loc.id == id;
    }, this.locations);
  }

  getLocationByPupCode(id) {
    return this.findLocInArray(function (loc) {
      return loc.pupCode == id;
    }, this.locations);
  }

  createElement(tag, classList = []) {
    let el = document.createElement(tag);
    if (classList.length) {
      classList.forEach(elClass => el.classList.add(elClass))
    }
    return el;
  }

  setupLeafletMap(rootEl) {
    this._map = L.map(rootEl, { zoomControl: false, minZoom: 4 });
    new L.Control.Zoom({ position: 'bottomright' }).addTo(this._map);

    let Icon = L.Icon.extend({
      options: {
        iconSize: [29, 34],
        iconAnchor: [15, 34],
        popupAnchor: [-3, -76]
      }
    });
    this._pickupIcon = new Icon({ iconUrl: this.images_url + 'marker.png' });

    L.tileLayer('https://map.plugins.itella.com/tile/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.mijora.lt">Mijora</a>' +
        ' | Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
    }).addTo(this._map);
    this._markerLayer = L.featureGroup();
    this._map.addLayer(this._markerLayer);
    return this;
  }

  setMapView(targetLatLng, targetZoom) {
    if (window.matchMedia("(min-width: 769px)").matches) {
      let offset = this.getElClientWidth(this.UI.container.getElementsByClassName('itella-card')[0]) / 2;
      let targetPoint = this._map.project(targetLatLng, targetZoom).subtract([offset, 0]);
      targetLatLng = this._map.unproject(targetPoint, targetZoom);
    }
    this._map.setView(targetLatLng, targetZoom);
  }

  getElClientWidth(el) {
    return el.clientWidth;
  }

  addMarker(latLong, id) {
    let marker = L.marker(latLong, { icon: this._pickupIcon, pickupPointId: id });
    this._markerLayer.addLayer(marker);
    return marker;
  }

  updateMapMarkers() {
    let _this = this;
    if (this._markerLayer !== null) {
      this._markerLayer.clearLayers();
    }

    /* add markers to marker layer and link icon in locations list */
    this.locations.forEach(function (location) {
      location._marker = _this.addMarker(location.location, location.id);
    });

    return this;
  }

  sortByCity(a, b) {
    let result = a.address.municipality.toLowerCase().localeCompare(b.address.municipality.toLowerCase());
    if (result == 0) {
      result = a.publicName.toLowerCase().localeCompare(b.publicName.toLowerCase());
    }
    return result;
  }

  sortByDistance(a, b) {
    return a.distance - b.distance;
  }

  setLocations(locations, update = true) {
    /* clone for inner use */
    this.locations = Array.isArray(locations) ? JSON.parse(JSON.stringify(locations)) : [];
    this.locations.sort(this.sortByCity);

    /* calculate defaultMapPos */
    let _latlongArray = [];
    this.locations.forEach(loc => {
      _latlongArray.push(loc.location);
    });
    if (_latlongArray.length > 0) {
      let bounds = new L.LatLngBounds(_latlongArray);
      this._defaultMapPos = bounds.getCenter();
    }

    if (update) {
      this.updateMapMarkers();
      this.updateDropdown();
    }
    return this;
  }

  getLocationCount() {
    return this.locations.length;
  }

  updateDropdown() {
    let _this = this;
    /**
     * @type HTMLElement
     */
    let dropdown = this.UI.modal.querySelector('.itella-select .dropdown-inner ul');

    if (!this.locations.length) {
      dropdown.innerHTML = '<li class="city">' + this.strings.no_pickup_points + '</li>';
      return this;
    }

    dropdown.innerHTML = '';
    let listHTML = [];
    let city = false;
    this.locations.forEach(function (loc) {
      if (city !== loc.address.municipality.toLowerCase()) {
        city = loc.address.municipality.toLowerCase();
        let cityEl = _this.createElement('li', ['city']);
        cityEl.innerText = loc.address.municipality;
        listHTML.push(cityEl);
      }

      /* check if we allready have html object, otherwise create new one */
      let li = Object.prototype.toString.call(loc._li) == '[object HTMLLIElement]' ? loc._li : _this.createElement('li');
      li.innerHTML = loc.publicName + ', ' + loc.address.address;
      if (typeof loc.distance != 'undefined') {
        let span = _this.createElement('span');
        span.innerText = loc.distance.toFixed(2);
        li.appendChild(span);
      }
      li.dataset.id = loc.id;
      listHTML.push(li);
      loc._li = li;
    });
    //dropdown.append(listHTML);
    let docFrag = document.createDocumentFragment();
    listHTML.forEach(el => docFrag.appendChild(el));
    dropdown.appendChild(docFrag);
    if (this.selectedPoint != null) {
      this.selectedPoint._li.classList.add('active');
    }

    return this;
  }

  setCountry(country_iso2_code) {
    this.country = country_iso2_code;
    return this;
  }

  setDebug(isOn = false) {
    this._isDebug = isOn;
    return this;
  }
}