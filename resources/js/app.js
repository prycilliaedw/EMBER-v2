import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { PMTiles } from 'pmtiles';
import {
    GeomType,
    LineSymbolizer,
    PolygonSymbolizer,
    leafletLayer,
} from 'protomaps-leaflet';


const EmberMap = {
    map: null,
    markerLayer: null,
    boundaryLayers: [],
    boundaryHoverTooltip: null,
    boundaryHoverFrame: null,
    pendingBoundaryHoverEvent: null,
    boundaryPointFocus: false,
    focusedLocationLabel: null,
    boundarySelection: {
        province: null,
        regency: null,
        district: null,
        village: null,
    },
    locations: [],
    language: 'id',
    selectedYear: 'all',
    activeStatusKeys: new Set(['high', 'medium', 'low', 'unrated']),
    activeConfidenceKeys: new Set(['low', 'nominal', 'high', 'unrated']),
    activeFsiKeys: new Set(['very_low', 'low', 'moderate', 'high', 'very_high', 'unrated']),
    activeLandCover: 'all',
    preserveQueryProvinceOnInitialZoom: false,

    initializeQueryState() {
        const params = new URLSearchParams(window.location.search);
        const yearRange = document.getElementById('map-year-range');
        const years = yearRange?.dataset.yearValues
            ? yearRange.dataset.yearValues.split(',').filter(Boolean)
            : [];
        const requestedYear = params.get('year');

        this.selectedYear = requestedYear && years.includes(requestedYear)
            ? requestedYear
            : 'all';

        const allowedConfidence = ['low', 'nominal', 'high', 'unrated'];
        if (params.has('confidence')) {
            this.activeConfidenceKeys = new Set(
                params.get('confidence').split(',').filter((key) => allowedConfidence.includes(key)),
            );
        }

        const allowedFsi = ['very_low', 'low', 'moderate', 'high', 'very_high', 'unrated'];
        if (params.has('fsi')) {
            this.activeFsiKeys = new Set(
                params.get('fsi').split(',').filter((key) => allowedFsi.includes(key)),
            );
        }

        const landCoverSelect = document.getElementById('map-land-cover-filter');
        const requestedLandCover = params.get('land_cover');
        this.activeLandCover = requestedLandCover
            && Array.from(landCoverSelect?.options || []).some((option) => option.value === requestedLandCover)
            ? requestedLandCover
            : 'all';

        const requestedProvince = params.get('province');
        this.boundarySelection.province = requestedProvince
            && this.locations.some((location) => String(location.provinsi || '').trim().toLocaleLowerCase('id-ID')
                === requestedProvince.trim().toLocaleLowerCase('id-ID'))
            ? requestedProvince
            : null;
        this.preserveQueryProvinceOnInitialZoom = Boolean(this.boundarySelection.province);
    },

    syncQueryState() {
        const url = new URL(window.location.href);
        const setOrDelete = (key, value, isDefault) => {
            if (isDefault) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        };

        setOrDelete('year', this.selectedYear, this.selectedYear === 'all');
        setOrDelete(
            'confidence',
            ['low', 'nominal', 'high', 'unrated'].filter((key) => this.activeConfidenceKeys.has(key)).join(','),
            this.activeConfidenceKeys.size === 4,
        );
        setOrDelete(
            'fsi',
            ['very_low', 'low', 'moderate', 'high', 'very_high', 'unrated'].filter((key) => this.activeFsiKeys.has(key)).join(','),
            this.activeFsiKeys.size === 6,
        );
        setOrDelete('land_cover', this.activeLandCover, this.activeLandCover === 'all');
        setOrDelete('province', this.boundarySelection.province || '', !this.boundarySelection.province);

        window.history.replaceState(window.history.state, '', url);
        document.querySelectorAll('[data-language-switch]').forEach((link) => {
            const languageUrl = new URL(url);
            languageUrl.searchParams.set('lang', link.dataset.languageSwitch);
            link.href = languageUrl.toString();
        });
        document.querySelectorAll('[data-map-statistics-link]').forEach((link) => {
            const statisticsUrl = new URL(url);
            statisticsUrl.pathname = '/statistics';
            link.href = statisticsUrl.toString();
        });
    },

    init() {
        const mapElement = document.getElementById('ember-map');
        const dataElement = document.getElementById('ember-map-data');

        if (!mapElement || !dataElement || mapElement.dataset.initialized === 'true') {
            return;
        }

        mapElement.dataset.initialized = 'true';
        const payload = JSON.parse(dataElement.textContent || '{}');
        const rawLocations = Array.isArray(payload)
            ? payload
            : (payload.locations || []);

        this.locations = rawLocations.map((location) => ({
            ...location,

            latitude: Number(location.latitude),
            longitude: Number(location.longitude),
            confidence: location.confidence !== null && location.confidence !== undefined && String(location.confidence).trim() !== ''
                ? (Number.isFinite(Number(location.confidence)) ? Number(location.confidence) : String(location.confidence).trim())
                : null,

            land_cover_id: location.land_cover_id !== null && location.land_cover_id !== undefined
                ? Number(location.land_cover_id)
                : null,

            hotspot_evidence: location.hotspot_evidence !== null && location.hotspot_evidence !== undefined
                ? Number(location.hotspot_evidence)
                : null,

            historical_burn_evidence: location.historical_burn_evidence !== null && location.historical_burn_evidence !== undefined
                ? Number(location.historical_burn_evidence)
                : null,

            fsi_score: location.fsi_score !== null && location.fsi_score !== undefined
                ? Number(location.fsi_score)
                : null,

            land_cover: location.land_cover
                ? String(location.land_cover).trim()
                : '',

            fsi_class: location.fsi_class
                ? String(location.fsi_class).trim()
                : '',

            context_flag: location.context_flag
                ? String(location.context_flag).trim()
                : '',

            model_version: location.model_version
                ? String(location.model_version).trim()
                : '',
        }));

        const locations = this.locations;
        this.language = payload.language === 'en' ? 'en' : 'id';
        this.initializeQueryState();

        this.map = L.map(mapElement, {
            scrollWheelZoom: false,
        }).setView([-2.5, 118], 5);

        ['map-year-filter', 'map-status-filter', 'map-drilldown-control', 'map-statistics-panel'].forEach((controlId) => {
            const control = document.getElementById(controlId);
            if (!control) return;

            L.DomEvent.disableClickPropagation(control);
            L.DomEvent.disableScrollPropagation(control);
        });

        L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            {
                maxZoom: 20,
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            },
        ).addTo(this.map);

        this.map.createPane('boundaries');
        this.map.getPane('boundaries').style.zIndex = '350';
        this.renderBoundaryLayers(payload.boundaryLayers || []);

        this.markerLayer = L.layerGroup().addTo(this.map);
        this.renderMarkers(locations, { fitView: true });
        this.initializeYearFilter();
        this.initializeStatusFilter();
        this.initializeBoundaryDrilldown();
        this.initializeBoundaryHover();
        this.map.on('click', (event) => {
            this.handleBoundaryClick(event);
            this.closeLocationDetail();
        });
        this.applyLocationFilters();
    },

    async renderBoundaryLayers(layers) {
        for (const layerDefinition of layers) {
            try {
                const archive = new PMTiles(layerDefinition.url);
                const metadata = await archive.getMetadata();
                const sourceLayers = Array.isArray(metadata?.vector_layers)
                    ? metadata.vector_layers.map((layer) => layer.id).filter(Boolean)
                    : [];

                if (sourceLayers.length === 0) {
                    console.warn(`Layer ${layerDefinition.name} tidak memiliki vector_layers.`);
                    continue;
                }

                const paintRules = sourceLayers.flatMap((dataLayer) => [
                    {
                        dataLayer,
                        filter: (_zoom, feature) => feature.geomType === GeomType.Polygon
                            && this.featureMatchesBoundarySelection(feature.props, layerDefinition.level),
                        symbolizer: new PolygonSymbolizer({
                            fill: '#dc2626',
                            opacity: layerDefinition.level === 'province' ? 0.08 : 0.045,
                        }),
                    },
                    {
                        dataLayer,
                        filter: (_zoom, feature) => feature.geomType !== GeomType.Point
                            && this.featureMatchesBoundarySelection(feature.props, layerDefinition.level),
                        symbolizer: new LineSymbolizer({
                            color: '#b91c1c',
                            width: layerDefinition.level === 'province' ? 1.8 : 1,
                            opacity: 0.7,
                            lineJoin: 'round',
                        }),
                    },
                ]);

                const boundaryLayer = leafletLayer({
                    url: archive,
                    paintRules,
                    labelRules: [],
                    pane: 'boundaries',
                    minZoom: Number(layerDefinition.minZoom ?? 0),
                    maxZoom: Number(layerDefinition.maxZoom ?? 20),
                    noWrap: true,
                    attribution: 'Batas wilayah',
                });

                boundaryLayer.addTo(this.map);
                this.boundaryLayers.push({
                    definition: layerDefinition,
                    layer: boundaryLayer,
                });
            } catch (error) {
                console.error(`Layer ${layerDefinition.name} gagal dimuat.`, error);
            }
        }
    },

    initializeBoundaryDrilldown() {
        document.getElementById('map-boundary-reset')?.addEventListener('click', () => {
            this.resetBoundaryDrilldown();
        });

        this.map.on('zoomend', () => {
            const zoom = this.map.getZoom();

            if (zoom <= 6) {
                if (this.preserveQueryProvinceOnInitialZoom) {
                    this.preserveQueryProvinceOnInitialZoom = false;
                } else {
                    this.boundarySelection = { province: null, regency: null, district: null, village: null };
                    this.boundaryPointFocus = false;
                    this.focusedLocationLabel = null;
                }
            } else if (zoom <= 8) {
                this.boundarySelection.regency = null;
                this.boundarySelection.district = null;
                this.boundarySelection.village = null;
            } else if (zoom <= 10) {
                this.boundarySelection.district = null;
                this.boundarySelection.village = null;
            }

            this.rerenderBoundaryLayers();
            this.updateBoundaryDrilldownUi();
            this.updateMapStatistics();
        });

        this.updateBoundaryDrilldownUi();
    },

    initializeBoundaryHover() {
        this.boundaryHoverTooltip = L.tooltip({
            className: 'ember-boundary-tooltip',
            direction: 'top',
            offset: [0, -10],
            opacity: 1,
        });

        this.map.on('mousemove', (event) => {
            this.pendingBoundaryHoverEvent = event;

            if (this.boundaryHoverFrame !== null) {
                return;
            }

            this.boundaryHoverFrame = window.requestAnimationFrame(() => {
                this.handleBoundaryHover(this.pendingBoundaryHoverEvent);
                this.boundaryHoverFrame = null;
            });
        });

        this.map.getContainer().addEventListener('mouseleave', () => this.hideBoundaryHover());
        this.map.on('zoomstart', () => this.hideBoundaryHover());
    },

    handleBoundaryHover(event) {
        const activeBoundary = this.activeBoundaryForZoom();

        if (!activeBoundary || !event) {
            this.hideBoundaryHover();
            return;
        }

        try {
            const pickedBySource = activeBoundary.layer.queryTileFeaturesDebug(
                event.latlng.lng,
                event.latlng.lat,
                3,
            );
            const pickedFeatures = Array.from(pickedBySource.values()).flat();
            const picked = pickedFeatures.find(({ feature }) =>
                feature.geomType === GeomType.Polygon
                && this.featureMatchesBoundarySelection(feature.props, activeBoundary.definition.level)
            );

            if (!picked) {
                this.hideBoundaryHover();
                return;
            }

            const propertyByLevel = {
                province: 'LEVEL_3',
                regency: 'LEVEL_4',
                district: 'LEVEL_5',
                village: 'LEVEL_6',
            };
            const labelByLevel = this.language === 'en'
                ? { province: 'Province', regency: 'Regency/City', district: 'District', village: 'Village' }
                : { province: 'Provinsi', regency: 'Kabupaten/Kota', district: 'Kecamatan', village: 'Desa' };
            const level = activeBoundary.definition.level;
            const name = picked.feature.props[propertyByLevel[level]];

            if (!name) {
                this.hideBoundaryHover();
                return;
            }

            const content = document.createElement('div');
            const typeElement = document.createElement('span');
            const nameElement = document.createElement('strong');
            typeElement.className = 'ember-boundary-tooltip-type';
            nameElement.className = 'ember-boundary-tooltip-name';
            typeElement.textContent = labelByLevel[level] || '';
            nameElement.textContent = String(name);
            content.append(typeElement, nameElement);

            this.boundaryHoverTooltip
                .setLatLng(event.latlng)
                .setContent(content);

            if (!this.map.hasLayer(this.boundaryHoverTooltip)) {
                this.boundaryHoverTooltip.addTo(this.map);
            }

            this.map.getContainer().style.cursor = 'pointer';
        } catch {
            this.hideBoundaryHover();
        }
    },

    hideBoundaryHover() {
        if (this.boundaryHoverTooltip && this.map.hasLayer(this.boundaryHoverTooltip)) {
            this.map.removeLayer(this.boundaryHoverTooltip);
        }

        this.map.getContainer().style.cursor = '';
    },

    activeBoundaryForZoom() {
        const zoom = this.map.getZoom();

        return this.boundaryLayers.find(({ definition }) =>
            zoom >= Number(definition.minZoom) && zoom <= Number(definition.maxZoom)
        );
    },

    handleBoundaryClick(event) {
        const activeBoundary = this.activeBoundaryForZoom();

        if (!activeBoundary) {
            return;
        }

        const pickedBySource = activeBoundary.layer.queryTileFeaturesDebug(
            event.latlng.lng,
            event.latlng.lat,
            4,
        );
        const pickedFeatures = Array.from(pickedBySource.values()).flat();
        const picked = pickedFeatures.find(({ feature }) =>
            feature.geomType === GeomType.Polygon
            && this.featureMatchesBoundarySelection(feature.props, activeBoundary.definition.level)
        );

        if (!picked) {
            return;
        }

        const props = picked.feature.props;
        const level = activeBoundary.definition.level;

        if (level === 'province' && props.LEVEL_3) {
            this.boundarySelection = {
                province: String(props.LEVEL_3),
                regency: null,
                district: null,
                village: null,
            };
        } else if (level === 'regency' && props.LEVEL_4) {
            this.boundarySelection.regency = String(props.LEVEL_4);
            this.boundarySelection.district = null;
            this.boundarySelection.village = null;
        } else if (level === 'district' && props.LEVEL_5) {
            this.boundarySelection.district = String(props.LEVEL_5);
            this.boundarySelection.village = null;
        } else if (level === 'village' && props.LEVEL_6) {
            this.boundarySelection.village = String(props.LEVEL_6);
        } else {
            return;
        }

        this.rerenderBoundaryLayers();
        this.updateBoundaryDrilldownUi();
        this.updateMapStatistics();

        const levelOrder = ['province', 'regency', 'district', 'village'];
        const nextLevel = levelOrder[levelOrder.indexOf(level) + 1];
        const nextBoundary = this.boundaryLayers.find(({ definition }) => definition.level === nextLevel);

        if (nextBoundary) {
            this.map.setView(event.latlng, Number(nextBoundary.definition.minZoom));
        }
    },

    featureMatchesBoundarySelection(props, level) {
        if (level === 'province' || level === 'other' || this.boundaryPointFocus) {
            return true;
        }

        if (!this.boundarySelection.province || String(props.LEVEL_3 ?? '') !== this.boundarySelection.province) {
            return false;
        }

        if (level === 'regency') {
            return true;
        }

        if (!this.boundarySelection.regency || String(props.LEVEL_4 ?? '') !== this.boundarySelection.regency) {
            return false;
        }

        if (level === 'district') {
            return true;
        }

        return Boolean(this.boundarySelection.district)
            && String(props.LEVEL_5 ?? '') === this.boundarySelection.district;
    },

    rerenderBoundaryLayers() {
        this.boundaryLayers.forEach(({ layer }) => layer.rerenderTiles());
    },

    resetBoundaryDrilldown() {
        this.boundarySelection = { province: null, regency: null, district: null, village: null };
        this.boundaryPointFocus = false;
        this.focusedLocationLabel = null;
        this.rerenderBoundaryLayers();
        this.updateBoundaryDrilldownUi();
        this.updateMapStatistics();
        this.map.fitBounds([[-6.5, 94.5], [6.5, 106.5]], { padding: [36, 36], maxZoom: 6 });
    },

    updateBoundaryDrilldownUi() {
        const labels = {
            province: this.language === 'en' ? 'Province' : 'Provinsi',
            regency: this.language === 'en' ? 'Regency/City' : 'Kabupaten/Kota',
            district: this.language === 'en' ? 'District' : 'Kecamatan',
            village: this.language === 'en' ? 'Village' : 'Desa',
        };
        const zoom = this.map.getZoom();
        const activeLevel = this.boundaryLayers.find(({ definition }) =>
            zoom >= Number(definition.minZoom) && zoom <= Number(definition.maxZoom)
        )?.definition.level || 'province';
        const breadcrumb = (this.boundaryPointFocus
            ? [this.language === 'en' ? 'Location point' : 'Titik lokasi', this.focusedLocationLabel]
            : ['Sumatera', this.boundarySelection.province, this.boundarySelection.regency, this.boundarySelection.district, this.boundarySelection.village])
            .filter(Boolean)
            .join(' / ');
        const parentReady = this.boundaryPointFocus || activeLevel === 'province'
            || (activeLevel === 'regency' && this.boundarySelection.province)
            || (activeLevel === 'district' && this.boundarySelection.regency)
            || (activeLevel === 'village' && this.boundarySelection.district);
        const instruction = this.boundaryPointFocus
            ? (this.language === 'en'
                ? 'Showing village boundaries around the selected location.'
                : 'Menampilkan batas desa di sekitar titik yang dipilih.')
            : parentReady
            ? (this.language === 'en'
                ? `Click a ${labels[activeLevel].toLowerCase()} to continue.`
                : `Klik ${labels[activeLevel].toLowerCase()} untuk melanjutkan.`)
            : (this.language === 'en'
                ? 'Zoom out and select the parent region first.'
                : 'Perkecil peta dan pilih wilayah induk terlebih dahulu.');

        const levelElement = document.getElementById('map-boundary-level');
        const breadcrumbElement = document.getElementById('map-boundary-breadcrumb');
        const instructionElement = document.getElementById('map-boundary-instruction');
        const resetButton = document.getElementById('map-boundary-reset');

        if (levelElement) levelElement.textContent = labels[activeLevel] || labels.province;
        if (breadcrumbElement) breadcrumbElement.textContent = breadcrumb;
                if (instructionElement) instructionElement.textContent = instruction;
        resetButton?.classList.toggle('hidden', !this.boundarySelection.province && !this.boundaryPointFocus);
                this.syncQueryState();
    },

    renderMarkers(locations, { fitView = false } = {}) {
        this.markerLayer.clearLayers();

        const bounds = [];

        locations.forEach((location) => {
            const latitude = Number(location.latitude);
            const longitude = Number(location.longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            const confidence = location.confidence;
            const status = this.statusFor(confidence);
            const marker = L.circleMarker([latitude, longitude], {
                radius: 10,
                color: '#ffffff',
                weight: 3,
                fillColor: status.color,
                fillOpacity: 1,
                interactive: true,
                bubblingMouseEvents: false,
            });

            marker.on('click', () => {
                this.showLocationDetail(location, status);
                this.focusLocationAtVillageLevel(location);
            });
            const fsiText = Number.isFinite(Number(location.fsi_score))
                ? ` · FSI ${Number(location.fsi_score).toFixed(1)}`
                : '';
            marker.bindTooltip(
                `${location.desa || (this.language === 'en' ? 'Location point' : 'Titik lokasi')}${fsiText} · ${this.language === 'en' ? 'Click for details' : 'Klik untuk detail'}`,
                { direction: 'top', offset: [0, -8] },
            );
            marker.addTo(this.markerLayer);
            bounds.push([latitude, longitude]);
        });

        const countElement = document.getElementById('map-result-count');
        const emptyElement = document.getElementById('map-filter-empty');

        if (countElement) {
            countElement.textContent = `${locations.length} ${this.language === 'en' ? 'locations' : 'lokasi'}`;
        }

        emptyElement?.classList.toggle('hidden', locations.length > 0);

        if (fitView && bounds.length === 1) {
            this.map.fitBounds([
                [-6.5, 94.5],
                [6.5, 106.5],
            ], {
                padding: [36, 36],
                maxZoom: 6,
            });
        } else if (fitView && bounds.length > 1) {
            this.map.fitBounds(bounds, {
                padding: [36, 36],
                maxZoom: 10,
            });
        } else if (fitView) {
            this.map.setView([-2.5, 118], 5);
        }
    },

    focusLocationAtVillageLevel(location) {
        const latitude = Number(location.latitude);
        const longitude = Number(location.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        const hasCompleteHierarchy = Boolean(
            location.provinsi && location.kabupaten_kota && location.kecamatan
        );

        this.boundaryPointFocus = !hasCompleteHierarchy;
        this.focusedLocationLabel = location.desa
            || `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
        this.boundarySelection = {
            province: location.provinsi || null,
            regency: location.kabupaten_kota || null,
            district: location.kecamatan || null,
            village: location.desa || null,
        };

        this.rerenderBoundaryLayers();
        this.updateBoundaryDrilldownUi();
        this.updateMapStatistics();

        const villageLayer = this.boundaryLayers.find(({ definition }) => definition.level === 'village');
        const targetZoom = villageLayer
            ? Math.max(12, Number(villageLayer.definition.minZoom))
            : 12;

        this.map.setView([latitude, longitude], targetZoom, { animate: true });
    },

    initializeYearFilter() {
        const yearRange = document.getElementById('map-year-range');
        const yearLabel = document.getElementById('map-year-label');
        const yearControl = document.querySelector('.map-year-slider-control');
        const yearMarks = document.querySelectorAll('[data-year-mark]');
        const years = yearRange?.dataset.yearValues
            ? yearRange.dataset.yearValues.split(',').filter(Boolean)
            : [];

        if (!yearRange || !yearLabel || !yearControl) {
            return;
        }

        const initialIndex = this.selectedYear === 'all'
            ? years.length
            : years.indexOf(this.selectedYear);
        yearRange.value = String(initialIndex >= 0 ? initialIndex : years.length);
        const initialRatio = Number(yearRange.max) > 0
            ? Number(yearRange.value) / Number(yearRange.max)
            : 1;
        const initialProgress = initialRatio * 100;
        const initialEdgeOffset = (1 - (2 * initialRatio)) * 1.125;
        yearControl.style.setProperty('--slider-progress', `calc(${initialProgress}% + ${initialEdgeOffset}rem)`);
        yearMarks.forEach((mark) => {
            mark.dataset.active = mark.dataset.yearMark === this.selectedYear ? 'true' : 'false';
        });
        yearLabel.textContent = this.selectedYear === 'all'
            ? (this.language === 'en' ? 'All' : 'Semua')
            : this.selectedYear;

        ['pointerdown', 'mousedown', 'touchstart'].forEach((eventName) => {
            yearRange.addEventListener(eventName, (event) => event.stopPropagation(), { passive: true });
        });

        yearRange.addEventListener('input', () => {
            const selectedIndex = Number(yearRange.value);
            const selectedYear = selectedIndex === years.length ? 'all' : years[selectedIndex];
            this.selectedYear = selectedYear;
            const ratio = Number(yearRange.max) > 0
                ? selectedIndex / Number(yearRange.max)
                : 1;
            const progress = ratio * 100;
            const edgeOffset = (1 - (2 * ratio)) * 1.125;

            yearControl.style.setProperty('--slider-progress', `calc(${progress}% + ${edgeOffset}rem)`);
            yearMarks.forEach((mark) => {
                mark.dataset.active = mark.dataset.yearMark === selectedYear ? 'true' : 'false';
            });
            yearLabel.textContent = selectedYear === 'all'
                ? (this.language === 'en' ? 'All' : 'Semua')
                : selectedYear;
            yearRange.setAttribute('aria-valuetext', yearLabel.textContent);
            this.applyLocationFilters();
        });

        yearRange.setAttribute('aria-valuetext', yearLabel.textContent);

        document.getElementById('map-detail-close')?.addEventListener('click', () => this.closeLocationDetail());
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeLocationDetail();
            }
        });
    },

    initializeStatusFilter() {
        const confidenceButtons = Array.from(document.querySelectorAll('[data-map-confidence]'));
        const fsiButtons = Array.from(document.querySelectorAll('[data-map-fsi]'));
        const landCoverSelect = document.getElementById('map-land-cover-filter');
        const resetButton = document.querySelector('[data-map-filters-reset]');

        const updateButtonState = (button, active) => {
            button.setAttribute('aria-pressed', active ? 'true' : 'false');

            button.classList.toggle('border-red-500', active);
            button.classList.toggle('bg-red-50', active);
            button.classList.toggle('text-red-700', active);

            button.classList.toggle('border-slate-200', !active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-slate-600', !active);

            const icon = button.querySelector('svg');
            if (icon) {
                icon.classList.toggle('opacity-100', active);
                icon.classList.toggle('opacity-30', !active);
            }
        };

        const updateAllButtonStates = () => {
            confidenceButtons.forEach((button) => {
                const key = button.dataset.mapConfidence;
                updateButtonState(
                    button,
                    this.activeConfidenceKeys.has(key),
                );
            });

            fsiButtons.forEach((button) => {
                const key = button.dataset.mapFsi;
                updateButtonState(
                    button,
                    this.activeFsiKeys.has(key),
                );
            });
        };

        confidenceButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const key = button.dataset.mapConfidence;

                if (this.activeConfidenceKeys.has(key)) {
                    this.activeConfidenceKeys.delete(key);
                } else {
                    this.activeConfidenceKeys.add(key);
                }

                updateAllButtonStates();
                this.applyLocationFilters();
            });
        });

        fsiButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const key = button.dataset.mapFsi;

                if (this.activeFsiKeys.has(key)) {
                    this.activeFsiKeys.delete(key);
                } else {
                    this.activeFsiKeys.add(key);
                }

                updateAllButtonStates();
                this.applyLocationFilters();
            });
        });

        landCoverSelect?.addEventListener('change', (event) => {
            event.stopPropagation();

            this.activeLandCover = landCoverSelect.value || 'all';
            this.applyLocationFilters();
        });
        if (landCoverSelect) {
            landCoverSelect.value = this.activeLandCover;
        }

        resetButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            this.activeConfidenceKeys = new Set(['low', 'nominal', 'high', 'unrated']);
            this.activeFsiKeys = new Set([
                'very_low',
                'low',
                'moderate',
                'high',
                'very_high',
                'unrated',
            ]);
            this.selectedYear = 'all';
            this.activeLandCover = 'all';

            const yearRange = document.getElementById('map-year-range');
            if (yearRange) {
                yearRange.value = yearRange.max;
                yearRange.dispatchEvent(new Event('input'));
            }
            if (landCoverSelect) {
                landCoverSelect.value = 'all';
            }

            updateAllButtonStates();
            this.applyLocationFilters();
        });

        updateAllButtonStates();
    },

    fsiKey(fsiClass) {
        if (!fsiClass) {
            return 'unrated';
        }

        return String(fsiClass)
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '_');
    },

    /**
     * Menghasilkan data hotspot yang benar-benar sesuai
     * dengan semua filter aktif.
     *
     * Filter yang digunakan:
     * - Tahun
     * - Confidence NASA
     * - Kerawanan / FSI
     * - Tutupan lahan
     */
    getFilteredLocations() {
        return this.locations.filter((location) => {
            const matchesYear = this.selectedYear === 'all'
                || (
                    location.date
                    && String(location.date).slice(0, 4) === String(this.selectedYear)
                );

            const confidenceClass =
                location.confidence_class ||
                this.confidenceClass(location.confidence) ||
                'unrated';

            const fsiClass = this.fsiKey(location.fsi_class);

            const matchesConfidence = confidenceClass
                ? this.activeConfidenceKeys.has(confidenceClass)
                : false;

            const matchesFsi = this.activeFsiKeys.has(fsiClass);

            const matchesLandCover = this.activeLandCover === 'all'
                || String(location.land_cover || '') === this.activeLandCover;

            return (
                matchesYear
                && matchesConfidence
                && matchesFsi
                && matchesLandCover
            );
        });
    },

    /**
     * Terapkan semua filter ke marker peta
     * dan statistik.
     */
    applyLocationFilters() {
        this.syncQueryState();
        const filteredLocations = this.getFilteredLocations();

        this.renderMarkers(filteredLocations, {
            fitView: false,
        });

        this.updateMapStatistics();

        this.closeLocationDetail();
    },

    /**
     * Update panel statistik berdasarkan DATA YANG SUDAH DIFILTER.
     *
     * Penting:
     * Statistik dan marker sekarang memakai dataset hasil filter
     * yang sama.
     */
    updateMapStatistics() {
        const panel = document.getElementById('map-statistics-panel');
        const donut = document.getElementById('map-yearly-donut');
        const totalElement = document.getElementById('map-yearly-total');
        const regionElement = document.getElementById('map-statistics-region');
        const periodElement = document.getElementById('map-statistics-period');
        const monthlySection = document.getElementById('map-monthly-statistics');

        if (
            !panel
            || !donut
            || !totalElement
            || !regionElement
            || !periodElement
            || !monthlySection
        ) {
            return;
        }

        this.updateClassStatistics();

        const statusDefinitions = {
            high: {
                color: '#b91c1c',
            },

            nominal: {
                color: '#d97706',
            },

            low: {
                color: '#047857',
            },

            unrated: {
                color: '#334155',
            },
        };

        const normalizeRegion = (value) => {
            return String(value || '')
                .trim()
                .toLocaleLowerCase('id-ID');
        };

        const selectedProvince = this.boundarySelection.province;
        const selectedProvinceKey = normalizeRegion(selectedProvince);

        const formatter = new Intl.NumberFormat(
            this.language === 'en'
                ? 'en-US'
                : 'id-ID'
        );

        /**
         * Ambil SEMUA hotspot yang sudah melewati:
         * 1. Tahun
         * 2. Confidence
         * 3. FSI
         * 4. Tutupan lahan
         */
        let filteredLocations = this.getFilteredLocations();

        /**
         * Kalau user sedang memilih provinsi,
         * statistik ikut dibatasi ke provinsi tersebut.
         */
        if (selectedProvinceKey) {
            filteredLocations = filteredLocations.filter((location) => {
                return normalizeRegion(location.provinsi) === selectedProvinceKey;
            });
        }

        /**
         * Hitung jumlah berdasarkan STATUS CONFIDENCE NASA.
         *
         * Jadi:
         * - Tinggi    = confidence >= 80
         * - Nominal   = confidence 30–79
         * - Rendah    = confidence < 30
         * - Belum dinilai = confidence tidak valid
         */
        const counts = Object.fromEntries(
            Object.keys(statusDefinitions).map((key) => [key, 0])
        );

        filteredLocations.forEach((location) => {
            const statusKey =
                this.confidenceClass(location.confidence) || 'unrated';

            if (Object.prototype.hasOwnProperty.call(counts, statusKey)) {
                counts[statusKey] += 1;
            }
        });

        /**
         * Total hotspot = jumlah data yang benar-benar lolos semua filter.
         */
        const total = filteredLocations.length;

        /**
         * Buat donut chart.
         */
        let offset = 0;

        const stops = Object.entries(statusDefinitions).map(
            ([key, definition]) => {
                const start = offset;

                offset += total > 0
                    ? (counts[key] / total) * 100
                    : 0;
                                    return `${definition.color} ${start}% ${offset}%`;
            }
        );

        donut.style.background = total > 0
            ? `conic-gradient(${stops.join(', ')})`
            : 'conic-gradient(#e2e8f0 0% 100%)';

        donut.setAttribute(
            'aria-label',
            `${selectedProvince || 'Sumatera'}, `
            + `${this.selectedYear}: `
            + `${formatter.format(total)}`
        );

        /**
         * Tampilkan total hasil filtering.
         */
        totalElement.textContent = formatter.format(total);

        regionElement.textContent =
            selectedProvince || 'Sumatera';

        periodElement.textContent =
            this.selectedYear === 'all'
                ? (
                    this.language === 'en'
                        ? 'All years'
                        : 'Semua tahun'
                )
                : String(this.selectedYear);

        /**
         * Tampilkan jumlah per status confidence.
         */
        Object.entries(counts).forEach(([key, count]) => {
            const countElement = panel.querySelector(
                `[data-map-yearly-count="${key}"]`
            );

            if (countElement) {
                const share = total > 0 ? (count / total) * 100 : 0;
                countElement.textContent = `${formatter.format(count)} · ${share.toFixed(1)}%`;
                countElement.parentElement.title = `${countElement.previousElementSibling?.textContent || key}: ${formatter.format(count)} / ${formatter.format(total)} (${share.toFixed(1)}%)`;
            }
        });

        /**
         * Statistik bulanan hanya muncul
         * ketika satu tahun dipilih.
         */
        const showMonthly = this.selectedYear !== 'all';

        monthlySection.classList.toggle(
            'hidden',
            !showMonthly
        );

        if (!showMonthly) {
            return;
        }

        /**
         * Siapkan data 12 bulan.
         */
        const monthlyCounts = Array.from(
            { length: 12 },
            () => ({
                high: 0,
                nominal: 0,
                low: 0,
                unrated: 0,
            })
        );

        /**
         * Gunakan DATA YANG SUDAH DIFILTER.
         *
         * Ini penting supaya grafik bulanan juga
         * mengikuti filter tutupan lahan, FSI,
         * confidence, dan tahun.
         */
        filteredLocations.forEach((location) => {
            const month = Number(
                String(location.date || '').slice(5, 7)
            );

            if (month < 1 || month > 12) {
                return;
            }

            const statusKey =
                this.confidenceClass(location.confidence)
                || 'unrated';

            if (
                Object.prototype.hasOwnProperty.call(
                    monthlyCounts[month - 1],
                    statusKey
                )
            ) {
                monthlyCounts[month - 1][statusKey] += 1;
            }
        });

        /**
         * Total per bulan.
         */
        const monthlyTotals = monthlyCounts.map(
            (monthCounts) => {
                return Object.values(monthCounts).reduce(
                    (sum, count) => sum + count,
                    0
                );
            }
        );

        const maxMonthlyTotal = Math.max(
            1,
            ...monthlyTotals
        );

        /**
         * Render grafik batang bulanan.
         */
        monthlyCounts.forEach(
            (monthCounts, monthIndex) => {
                const bar = panel.querySelector(
                    `[data-map-month-bar="${monthIndex}"]`
                );

                if (!bar) {
                    return;
                }

                const monthTotal =
                    monthlyTotals[monthIndex];

                bar.replaceChildren();

                bar.style.height =
                    monthTotal > 0
                        ? `${Math.max(
                            5,
                            (monthTotal / maxMonthlyTotal) * 100
                        )}%`
                        : '0';

                bar.title =
                    `${formatter.format(monthTotal)} `
                    + `${this.language === 'en'
                        ? 'locations'
                        : 'lokasi'}`;

                Object.entries(statusDefinitions).forEach(
                    ([key, definition]) => {
                        const count = monthCounts[key];

                        if (count === 0 || monthTotal === 0) {
                            return;
                        }

                        const segment =
                            document.createElement('span');

                        segment.style.height =
                            `${(count / monthTotal) * 100}%`;

                        segment.style.background =
                            definition.color;

                        segment.title =
                            `${key}: `
                            + `${formatter.format(count)}`;

                        bar.append(segment);
                    }
                );
            }
        );

        /**
         * Judul dan total grafik bulanan.
         */
        const monthlyTitle =
            document.getElementById(
                'map-monthly-title'
            );

        const monthlyTotal =
            document.getElementById(
                'map-monthly-total'
            );

        if (monthlyTitle) {
            monthlyTitle.textContent =
                `${selectedProvince || 'Sumatera'} · ${this.selectedYear}`;
        }

        if (monthlyTotal) {
            monthlyTotal.textContent =
                formatter.format(total);
        }
    },

    updateClassStatistics() {
        const root = document.getElementById('map-class-statistics');
        if (!root) return;
        const evidence = JSON.parse(root.dataset.classEvidence || '{}');
        const definitions = JSON.parse(root.dataset.classDistribution || '[]');
        let filtered = this.getFilteredLocations();
        if (this.boundarySelection.province) {
            const province = String(this.boundarySelection.province).trim().toLocaleLowerCase('id-ID');
            filtered = filtered.filter((location) => String(location.provinsi || '').trim().toLocaleLowerCase('id-ID') === province);
        }
        const counts = Object.fromEntries(Object.keys(evidence).map((id) => [id, 0]));
        filtered.forEach((location) => {
            const id = location.land_cover_id == null ? null : String(location.land_cover_id);
            if (id && Object.prototype.hasOwnProperty.call(counts, id)) counts[id] += 1;
            else {
                const match = Object.entries(evidence).find(([, item]) => item.name === location.land_cover);
                if (match) counts[match[0]] += 1;
            }
        });
        const total = Object.values(counts).reduce((sum, value) => sum + value, 0);
        const formatter = new Intl.NumberFormat(this.language === 'en' ? 'en-US' : 'id-ID');
        const selected = this.activeLandCover !== 'all'
            ? definitions.find((item) => item.name === this.activeLandCover)
            : null;
        const legend = document.getElementById('map-class-legend');
        const donut = document.getElementById('map-class-donut');
        const clear = document.getElementById('map-class-clear');
        const selectedName = document.getElementById('map-selected-class-name');
        const selectedSummary = document.getElementById('map-selected-class-summary');
        const selectedArea = document.getElementById('map-selected-class-area');
        const bars = document.getElementById('map-selected-bars');
        const metricCards = document.getElementById('map-class-metrics');
        const overallNote = document.getElementById('map-overall-metrics-note');
        const values = [
            ['map-class-hse', selected ? evidence[selected.id]?.hse : null],
            ['map-class-hbe', selected ? evidence[selected.id]?.hbe : null],
            ['map-class-fsi', selected ? evidence[selected.id]?.fsi : null],
        ];
        const selectedCount = selected ? (counts[selected.id] || 0) : 0;
        const displayed = definitions;
        let offset = 0;
        const stops = displayed.map((item) => {
            const count = counts[item.id] || 0;
            const share = total ? count / total * 100 : 0;
            const start = offset;
            offset += share;
            return `${item.color} ${start}% ${offset}%`;
        });
        donut.style.background = total ? `conic-gradient(${stops.join(',')})` : '#e2e8f0';
        donut.setAttribute('aria-label', `${this.language === 'en' ? 'Hotspot distribution by land-cover class' : 'Distribusi hotspot per kelas tutupan lahan'} · ${formatter.format(total)} ${this.language === 'en' ? 'hotspots' : 'hotspot'}`);
        legend.replaceChildren();
        displayed.forEach((item) => {
            const count = counts[item.id] || 0;
            const share = total ? count / total * 100 : 0;
            const row = document.createElement('button');
            row.type = 'button';
            row.className = `flex items-center gap-1 rounded-sm px-1 text-left text-slate-700 ${selected?.id === item.id ? 'bg-red-50 ring-1 ring-red-200' : ''}`;
            row.title = `${item.display_name || item.name}: ${formatter.format(count)} (${share.toFixed(1)}%) / ${formatter.format(total)}`;
            const swatch = document.createElement('span');
            swatch.className = 'size-2 shrink-0 rounded-full';
            swatch.style.background = item.color;
            const name = document.createElement('span');
            name.className = 'min-w-0 flex-1 truncate';
            name.textContent = item.display_name || item.name;
            const value = document.createElement('strong');
            value.textContent = `${formatter.format(count)} · ${share.toFixed(1)}%`;
            row.append(swatch, name, value);
            row.addEventListener('click', () => {
                const filter = document.getElementById('map-land-cover-filter');
                if (filter) { filter.value = item.name; filter.dispatchEvent(new Event('change')); }
            });
            legend.append(row);
        });
        clear.classList.toggle('hidden', !selected);
        metricCards.style.display = selected ? 'grid' : 'none';
        overallNote.classList.toggle('hidden', Boolean(selected));
        selectedSummary.classList.toggle('hidden', !selected);
        selectedName.textContent = selected ? (selected.display_name || selected.name) : '';
        const area = selected ? evidence[selected.id]?.area_km2 : null;
        if (selectedArea) {
            selectedArea.textContent = selected
                ? `${formatter.format(selectedCount)} ${this.language === 'en' ? 'hotspots' : 'hotspot'} · ${area == null ? (this.language === 'en' ? '2024 area unavailable' : 'luas 2024 tidak tersedia') : `${this.language === 'en' ? '2024 area' : 'luas 2024'} ${new Intl.NumberFormat(this.language === 'en' ? 'en-US' : 'id-ID', { maximumFractionDigits: 3 }).format(area)} km²`}`
                : '';
        }
        values.forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value == null ? '—' : `${formatter.format(value)} / 100`;
        });
        root.querySelectorAll('[data-score-class]').forEach((row) => {
            const isSelected = selected && row.dataset.scoreClass === String(selected.id);
            row.classList.toggle('bg-red-50', Boolean(isSelected));
            row.classList.toggle('ring-1', Boolean(isSelected));
            row.classList.toggle('ring-red-200', Boolean(isSelected));
            row.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            row.onclick = () => {
                const item = definitions.find((definition) => String(definition.id) === row.dataset.scoreClass);
                const filter = document.getElementById('map-land-cover-filter');
                if (item && filter) {
                    filter.value = item.name;
                    filter.dispatchEvent(new Event('change'));
                }
            };
        });
        bars.classList.toggle('hidden', !selected);
        bars.replaceChildren();
        if (selected) {
            if (selectedCount === 0) {
                const empty = document.createElement('p');
                empty.className = 'mb-2 text-[9px] text-slate-500';
                empty.textContent = this.language === 'en' ? 'No hotspots match the active filters. Class-level evidence scores are shown below.' : 'Tidak ada hotspot yang sesuai dengan filter aktif. Skor bukti tingkat kelas ditampilkan di bawah.';
                bars.append(empty);
            }
            if (values.some(([, value]) => value != null)) {
                const title = document.createElement('p');
                title.className = 'mb-2 text-[9px] font-bold text-slate-500';
                title.textContent = this.language === 'en' ? 'Class evidence scores (0–100)' : 'Skor bukti kelas (0–100)';
                bars.append(title);
                const scale = document.createElement('div');
                scale.className = 'mb-1 flex justify-between text-[7px] font-semibold text-slate-400';
                scale.innerHTML = '<span>0</span><span>100</span>';
                bars.append(scale);
                const chart = document.createElement('div');
                chart.className = 'grid h-28 grid-cols-3 items-end gap-3 border-b border-l border-slate-300 px-1';
                [['HSE', evidence[selected.id]?.hse, '#dc2626'], ['HBE', evidence[selected.id]?.hbe, '#d97706'], ['FSI', evidence[selected.id]?.fsi, '#2563eb']].forEach(([label, value, color]) => {
                    const col = document.createElement('div');
                    col.className = 'flex h-full flex-col items-center justify-end gap-1';
                    col.setAttribute('role', 'img');
                    col.setAttribute('aria-label', `${label}: ${value == null ? (this.language === 'en' ? 'No data' : 'Tidak tersedia') : `${formatter.format(value)} / 100`}`);
                    col.title = `${label}: ${value ?? (this.language === 'en' ? 'No data' : 'Tidak tersedia')} / 100`;
                    const v = document.createElement('span'); v.className = 'text-[8px] font-bold'; v.textContent = value == null ? '—' : formatter.format(value);
                    const bar = document.createElement('span'); bar.className = 'w-5'; bar.style.height = `${value == null ? 0 : Math.max(0, Math.min(100, Number(value)))}%`; bar.style.background = color;
                    const name = document.createElement('span'); name.className = 'text-[8px]'; name.textContent = label;
                    col.append(v, bar, name); chart.append(col);
                });
                bars.append(chart);
            }
        }
        clear.onclick = () => {
            const filter = document.getElementById('map-land-cover-filter');
            if (filter) { filter.value = 'all'; filter.dispatchEvent(new Event('change')); }
        };
    },

    confidenceClass(confidence) {
        if (confidence === null || confidence === undefined || String(confidence).trim() === '') {
            return null;
        }

        if (!Number.isFinite(Number(confidence))) {
            const normalized = String(confidence).trim().toLowerCase();
            return ({
                high: 'high',
                tinggi: 'high',
                nominal: 'nominal',
                medium: 'nominal',
                sedang: 'nominal',
                low: 'low',
                rendah: 'low',
            })[normalized] || null;
        }

        const value = Number(confidence);

        if (value < 30) {
            return 'low';
        }

        if (value < 80) {
            return 'nominal';
        }

        return 'high';
    },

    statusFor(confidence) {
        if (confidence === null || confidence === undefined || String(confidence).trim() === '') {
            return {
                key: 'unrated',
                label: this.language === 'en' ? 'Unrated' : 'Belum dinilai',
                color: '#64748b',
            };
        }

        const numericValue = Number(confidence);

        if (!Number.isFinite(numericValue)) {
            return {
                key: 'unrated',
                label: this.language === 'en' ? 'Unrated' : 'Belum dinilai',
                color: '#64748b',
            };
        }

        if (numericValue >= 80) {
            return {
                key: 'high',
                label: this.language === 'en' ? 'High' : 'Tinggi',
                color: '#ef4444',
            };
        }

        if (numericValue >= 30) {
            return {
                key: 'nominal',
                label: 'Nominal',
                color: '#f59e0b',
            };
        }

        return {
            key: 'low',
            label: this.language === 'en' ? 'Low' : 'Rendah',
            color: '#10b981',
        };
    },

    showLocationDetail(location, status) {
        const panel = document.getElementById('map-detail-panel');

        if (!panel) {
            return;
        }

        const fsiScore = Number(location.fsi_score);
        const hotspotEvidence = Number(location.hotspot_evidence);
        const historicalBurnEvidence = Number(location.historical_burn_evidence);

        const values = {
            title: location.desa
                || (this.language === 'en' ? 'Village not available' : 'Desa belum tersedia'),

            region: [
                location.kecamatan,
                location.kabupaten_kota,
                location.provinsi,
            ].filter(Boolean).join(', ') || '-',

            confidence: Number.isFinite(Number(location.confidence))
                ? `${Number(location.confidence).toFixed(0)} / 100`
                : '-',

            status: status.label,

            fsi: Number.isFinite(fsiScore)
                ? `${fsiScore.toFixed(1)} / 100`
                : (this.language === 'en' ? 'Not assessed' : 'Belum dinilai'),

            fsiClass: (() => {
                const fsiClass = String(location.fsi_class || '').trim();

                if (!fsiClass) {
                    return this.language === 'en'
                        ? 'Not assessed'
                        : 'Belum dinilai';
                }

                if (this.language === 'en') {
                    return fsiClass;
                }

                return {
                    'Very Low': 'Sangat Rendah',
                    'Low': 'Rendah',
                    'Moderate': 'Sedang',
                    'High': 'Tinggi',
                    'Very High': 'Sangat Tinggi',
                }[fsiClass] || fsiClass;
            })(),

            landCover: location.land_cover || '-',

            hotspotEvidence: Number.isFinite(hotspotEvidence)
                ? `${hotspotEvidence.toFixed(1)} / 100`
                : '-',

            historicalBurnEvidence: Number.isFinite(historicalBurnEvidence)
                ? `${historicalBurnEvidence.toFixed(1)} / 100`
                : '-',

            province: location.provinsi || '-',
            regency: location.kabupaten_kota || '-',
            district: location.kecamatan || '-',
            village: location.desa || '-',
            date: this.formatDate(location.date),
            coordinates: `${location.latitude}, ${location.longitude}`,
        };

        Object.entries(values).forEach(([key, value]) => {
            const element = document.getElementById(`map-detail-${key}`);

            if (element) {
                element.textContent = value;
            }
        });

        const link = document.getElementById('map-detail-link');

        if (link && location.detail_url) {
            link.href = location.detail_url;
        }

        const flagElement = document.getElementById('map-detail-contextFlag');

        if (flagElement) {
            const flag = String(location.context_flag || '').trim();
            const translatedFlag = {
                'Evidence Aligned': this.language === 'en' ? 'Evidence aligned' : 'Evidence selaras',
                'Evidence Divergence': this.language === 'en' ? 'Evidence divergence' : 'Evidence berbeda',
                'Review': this.language === 'en' ? 'Review required' : 'Perlu ditinjau',
                'Consistent': this.language === 'en' ? 'Consistent' : 'Konsisten',
            };
            flagElement.textContent = translatedFlag[flag] || flag || (this.language === 'en' ? 'Not assessed' : 'Belum dinilai');
        }

        panel.dataset.open = 'true';
        panel.setAttribute('aria-hidden', 'false');
        panel.scrollTop = 0;
    },

    closeLocationDetail() {
        const panel = document.getElementById('map-detail-panel');

        if (!panel) {
            return;
        }

        panel.dataset.open = 'false';
        panel.setAttribute('aria-hidden', 'true');
    },

    formatDate(date) {
        if (!date) {
            return '-';
        }

        return new Intl.DateTimeFormat(this.language === 'en' ? 'en-US' : 'id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(`${date}T00:00:00`));
    },
};

document.addEventListener('DOMContentLoaded', () => EmberMap.init());
document.addEventListener('livewire:navigated', () => EmberMap.init());

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-url]');

    if (!button) {
        return;
    }

    const originalLabel = button.textContent;

    try {
        await navigator.clipboard.writeText(button.dataset.copyUrl);
        button.textContent = EmberMap.language === 'en'
            ? 'URL copied'
            : 'URL tersalin';
        window.setTimeout(() => {
            button.textContent = originalLabel;
        }, 1600);
    } catch {
        window.prompt(
            EmberMap.language === 'en'
                ? 'Copy the following photo URL:'
                : 'Salin URL foto berikut:',
            button.dataset.copyUrl,
        );
    }
});

const initializeBackToTop = () => {
    const button = document.getElementById('back-to-top');

    if (!button || button.dataset.initialized === 'true') {
        return;
    }

    button.dataset.initialized = 'true';

    const updateVisibility = () => {
        const isVisible = window.scrollY > 400;

        button.classList.toggle('pointer-events-none', !isVisible);
        button.classList.toggle('opacity-0', !isVisible);
        button.classList.toggle('translate-y-3', !isVisible);
        button.classList.toggle('opacity-100', isVisible);
        button.classList.toggle('translate-y-0', isVisible);
    };

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', updateVisibility, { passive: true });
    updateVisibility();
};

document.addEventListener('DOMContentLoaded', initializeBackToTop);
document.addEventListener('livewire:navigated', initializeBackToTop);

const initializeLocationDetailMap = () => {
    const mapElement = document.getElementById('location-detail-map');
    const dataElement = document.getElementById('location-detail-map-data');

    if (!mapElement || !dataElement || mapElement.dataset.initialized === 'true') {
        return;
    }
        const location = JSON.parse(dataElement.textContent || '{}');
    const latitude = Number(location.latitude);
    const longitude = Number(location.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    mapElement.dataset.initialized = 'true';
    const detailMap = L.map(mapElement, { scrollWheelZoom: false }).setView([latitude, longitude], 11);

    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        {
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        },
    ).addTo(detailMap);

    const confidence = location.confidence;
    const status = EmberMap.statusFor(confidence);

    L.circleMarker([latitude, longitude], {
        radius: 10,
        color: '#ffffff',
        weight: 3,
        fillColor: status.color,
        fillOpacity: 1,
    }).addTo(detailMap);
};

document.addEventListener('DOMContentLoaded', initializeLocationDetailMap);
document.addEventListener('livewire:navigated', initializeLocationDetailMap);

const initializeRichContentSliders = () => {
    document.querySelectorAll('.rich-content .tmce-slider').forEach((slider) => {
        if (slider.dataset.initialized === 'true') return;

        const slides = Array.from(slider.querySelectorAll('.tmce-slides > figure'));

        if (slides.length === 0) return;

        slider.dataset.initialized = 'true';
        let currentIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('active')));

        const showSlide = (index) => {
            currentIndex = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('active', slideIndex === currentIndex));
            slider.dataset.index = String(currentIndex);
        };

        slider.querySelector('.prev')?.addEventListener('click', () => showSlide(currentIndex - 1));
        slider.querySelector('.next')?.addEventListener('click', () => showSlide(currentIndex + 1));
        showSlide(currentIndex);
    });
};

document.addEventListener('DOMContentLoaded', initializeRichContentSliders);
document.addEventListener('livewire:navigated', initializeRichContentSliders);

const initializeRevealMotion = () => {
    const elements = document.querySelectorAll('[data-reveal]:not([data-motion-ready])');

    if (elements.length === 0) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -40px',
    });

    elements.forEach((element) => {
        element.dataset.motionReady = 'true';
        const delay = Number(element.dataset.revealDelay || 0);
        element.style.setProperty('--reveal-delay', `${Math.max(0, Math.min(delay, 500))}ms`);
        observer.observe(element);
    });
};

document.addEventListener('DOMContentLoaded', initializeRevealMotion);
document.addEventListener('livewire:navigated', initializeRevealMotion);

const initializeTeamCarousel = () => {
    document.querySelectorAll('[data-team-carousel]:not([data-initialized])').forEach((carousel) => {
        const cards = Array.from(carousel.querySelectorAll('[data-team-card]'));
        if (cards.length === 0) return;

        carousel.dataset.initialized = 'true';
        let activeIndex = 0;
        let touchStartX = null;

        const update = () => {
            cards.forEach((card, index) => {
                let offset = index - activeIndex;
                if (offset > cards.length / 2) offset -= cards.length;
                if (offset < -cards.length / 2) offset += cards.length;

                let position = 'hidden';
                if (offset === 0) position = 'active';
                else if (offset === -1) position = 'prev';
                else if (offset === 1) position = 'next';
                else if (offset === -2) position = 'prev-far';
                else if (offset === 2) position = 'next-far';

                card.dataset.position = position;
                card.setAttribute('aria-hidden', position === 'active' ? 'false' : 'true');
                card.tabIndex = position === 'active' ? 0 : -1;
            });

            const counter = carousel.querySelector('[data-team-current]');
            if (counter) counter.textContent = String(activeIndex + 1).padStart(2, '0');
        };

        const move = (direction) => {
            activeIndex = (activeIndex + direction + cards.length) % cards.length;
            update();
        };

        carousel.querySelector('[data-team-prev]')?.addEventListener('click', () => move(-1));
        carousel.querySelector('[data-team-next]')?.addEventListener('click', () => move(1));
        cards.forEach((card, index) => card.addEventListener('click', () => {
            if (index !== activeIndex) {
                activeIndex = index;
                update();
            }
        }));

        carousel.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') move(-1);
            if (event.key === 'ArrowRight') move(1);
        });
        carousel.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0]?.clientX ?? null;
        }, { passive: true });
        carousel.addEventListener('touchend', (event) => {
            if (touchStartX === null) return;
            const distance = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
            if (Math.abs(distance) > 45) move(distance > 0 ? -1 : 1);
            touchStartX = null;
        }, { passive: true });

        update();
    });
};

document.addEventListener('DOMContentLoaded', initializeTeamCarousel);
document.addEventListener('livewire:navigated', initializeTeamCarousel);

const initializeProvinceTrendCharts = () => {
    document.querySelectorAll('[data-province-trend]:not([data-initialized])').forEach((root) => {
        const dataElement = root.querySelector('[data-province-trend-data]');
        const svg = root.querySelector('[data-province-chart]');
        const select = root.querySelector('[data-province-select]');
        const periodSelect = root.querySelector('[data-province-period]');
        const yearSelect = root.querySelector('[data-province-year]');
        const yearWrapper = root.querySelector('[data-province-year-wrapper]');
        const nameElement = root.querySelector('[data-province-chart-name]');
        const subtitleElement = root.querySelector('[data-province-chart-subtitle]');
        const totalElement = root.querySelector('[data-province-chart-total]');
        const options = Array.from(root.querySelectorAll('[data-province-option]'));

        if (!dataElement || !svg || !select || !periodSelect || !yearSelect) return;

        const payload = JSON.parse(dataElement.textContent || '{}');
        const years = Array.isArray(payload.years) ? payload.years : [];
        const provinces = Array.isArray(payload.provinces) ? payload.provinces : [];
        const monthLabels = Array.isArray(payload.months) ? payload.months : [];
        if (years.length === 0 || provinces.length === 0) return;

        root.dataset.initialized = 'true';
        const namespace = 'http://www.w3.org/2000/svg';
        const width = 900;
        const height = 330;
        const margin = { top: 28, right: 24, bottom: 48, left: 58 };
        const plotWidth = width - margin.left - margin.right;
        const plotHeight = height - margin.top - margin.bottom;

        const createSvgElement = (tag, attributes = {}) => {
            const element = document.createElementNS(namespace, tag);
            Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
            return element;
        };

        const render = (provinceIndex) => {
            const province = provinces[provinceIndex] || provinces[0];
            const isMonthly = periodSelect.value === 'monthly';
            const selectedYear = String(yearSelect.value || years.at(-1));
            const labels = isMonthly ? monthLabels : years;
            const values = isMonthly
                ? (Array.isArray(province.monthly?.[selectedYear]) ? province.monthly[selectedYear].map(Number) : Array(12).fill(0))
                : (Array.isArray(province.yearly) ? province.yearly.map(Number) : []);
            const maxValue = Math.max(1, ...values);
            const xFor = (index) => labels.length === 1
                ? margin.left + plotWidth / 2
                : margin.left + (index / (labels.length - 1)) * plotWidth;
            const yFor = (value) => margin.top + plotHeight - (value / maxValue) * plotHeight;

            svg.replaceChildren();
            nameElement.textContent = province.name;
            subtitleElement.textContent = isMonthly ? payload.monthlySubtitle : payload.yearlySubtitle;
            totalElement.textContent = values.reduce((sum, value) => sum + value, 0).toLocaleString('id-ID');
            select.value = String(provinceIndex);
            yearWrapper?.classList.toggle('hidden', !isMonthly);
            options.forEach((option) => {
                const optionIndex = Number(option.dataset.provinceOption);
                const optionProvince = provinces[optionIndex];
                const optionValues = isMonthly
                    ? (optionProvince?.monthly?.[selectedYear] || [])
                    : (optionProvince?.yearly || []);
                option.dataset.active = option.dataset.provinceOption === String(provinceIndex) ? 'true' : 'false';
                const total = option.querySelector('[data-province-option-total]');
                if (total) total.textContent = optionValues.reduce((sum, value) => sum + Number(value), 0).toLocaleString('id-ID');
            });

            const defs = createSvgElement('defs');
            const gradient = createSvgElement('linearGradient', { id: 'province-line-fill', x1: '0', y1: '0', x2: '0', y2: '1' });
            gradient.append(
                Object.assign(createSvgElement('stop', { offset: '0%', 'stop-color': '#ef4444', 'stop-opacity': '.32' })),
                Object.assign(createSvgElement('stop', { offset: '100%', 'stop-color': '#ef4444', 'stop-opacity': '0' })),
            );
            defs.append(gradient);
            svg.append(defs);

            for (let step = 0; step <= 4; step++) {
                const value = (maxValue / 4) * step;
                const y = yFor(value);
                svg.append(createSvgElement('line', {
                    x1: margin.left, y1: y, x2: width - margin.right, y2: y,
                    stroke: 'rgba(255,255,255,.09)', 'stroke-width': 1,
                }));
                const label = createSvgElement('text', {
                    x: margin.left - 12, y: y + 4, fill: '#64748b',
                    'font-size': 11, 'font-weight': 700, 'text-anchor': 'end',
                });
                label.textContent = String(Math.round(value));
                svg.append(label);
            }

            labels.forEach((labelValue, index) => {
                const label = createSvgElement('text', {
                    x: xFor(index), y: height - 18, fill: '#94a3b8',
                    'font-size': 11, 'font-weight': 700, 'text-anchor': 'middle',
                });
                label.textContent = String(labelValue);
                svg.append(label);
            });

            const points = values.map((value, index) => [xFor(index), yFor(value)]);
            const linePath = points.map(([x, y], index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`).join(' ');
            const areaPath = points.length > 0
                ? `M ${points[0][0]} ${margin.top + plotHeight} ${linePath.replace(/^M/, 'L')} L ${points.at(-1)[0]} ${margin.top + plotHeight} Z`
                : '';

            svg.append(createSvgElement('path', { d: areaPath, fill: 'url(#province-line-fill)' }));
            const path = createSvgElement('path', {
                d: linePath, fill: 'none', stroke: '#f87171', 'stroke-width': 4,
                'stroke-linecap': 'round', 'stroke-linejoin': 'round',
            });
            svg.append(path);

            const pathLength = path.getTotalLength();
            path.style.strokeDasharray = String(pathLength);
            path.style.strokeDashoffset = String(pathLength);
            path.style.transition = 'stroke-dashoffset 700ms cubic-bezier(.16, 1, .3, 1)';
            requestAnimationFrame(() => { path.style.strokeDashoffset = '0'; });

            points.forEach(([x, y], index) => {
                const marker = createSvgElement('g');
                marker.append(createSvgElement('circle', { cx: x, cy: y, r: 9, fill: '#0f172a', stroke: '#f87171', 'stroke-width': 3 }));
                marker.append(createSvgElement('circle', { cx: x, cy: y, r: 3, fill: '#fff' }));
                const title = createSvgElement('title');
                title.textContent = `${labels[index]}${isMonthly ? ` ${selectedYear}` : ''}: ${values[index]} ${payload.locationLabel}`;
                marker.append(title);

                const valueLabel = createSvgElement('text', {
                    x, y: Math.max(15, y - 16), fill: '#f8fafc',
                    'font-size': 12, 'font-weight': 800, 'text-anchor': 'middle',
                });
                valueLabel.textContent = String(values[index]);
                marker.append(valueLabel);
                svg.append(marker);
            });
        };

        select.addEventListener('change', () => render(Number(select.value)));
        periodSelect.addEventListener('change', () => render(Number(select.value)));
        yearSelect.addEventListener('change', () => render(Number(select.value)));
        options.forEach((option) => option.addEventListener('click', () => render(Number(option.dataset.provinceOption))));
        render(0);
    });
};

document.addEventListener('DOMContentLoaded', initializeProvinceTrendCharts);
document.addEventListener('livewire:navigated', initializeProvinceTrendCharts);

const initializeAnnualDonutCharts = () => {
    document.querySelectorAll('[data-annual-donut]:not([data-initialized])').forEach((root) => {
        const dataElement = root.querySelector('[data-annual-donut-data]');
        const chart = root.querySelector('[data-annual-donut-chart]');
        const yearSelect = root.querySelector('[data-annual-donut-year]');
        const yearLabel = root.querySelector('[data-annual-donut-year-label]');
        const totalElement = root.querySelector('[data-annual-donut-total]');

        if (!dataElement || !chart || !yearSelect || !yearLabel || !totalElement) return;

        const payload = JSON.parse(dataElement.textContent || '{}');
        const statistics = Array.isArray(payload.statistics) ? payload.statistics : [];
        const statuses = payload.statuses || {};
        const statusKeys = Object.keys(statuses);

        if (statistics.length === 0 || statusKeys.length === 0) return;

        root.dataset.initialized = 'true';
        const formatter = new Intl.NumberFormat(payload.locale || 'id-ID');

        const render = (selectedYear) => {
            const statistic = statistics.find((item) => String(item.year) === String(selectedYear)) || statistics.at(-1);
            let offset = 0;
            const stops = statusKeys.map((key) => {
                const percentage = Number(statistic.percentages?.[key] || 0);
                const start = offset;
                offset += percentage;

                return `${statuses[key].color} ${start}% ${offset}%`;
            });

            chart.style.background = offset > 0
                ? `conic-gradient(${stops.join(', ')})`
                : 'conic-gradient(#e2e8f0 0% 100%)';
            chart.setAttribute(
                'aria-label',
                `${statistic.year}: ${formatter.format(statistic.total)} ${payload.locale === 'en-US' ? 'total locations' : 'total lokasi'}`
            );
            yearLabel.textContent = statistic.year;
            totalElement.textContent = formatter.format(statistic.total);

            statusKeys.forEach((key) => {
                const item = root.querySelector(`[data-annual-donut-item="${key}"]`);
                if (!item) return;

                const count = item.querySelector('[data-annual-donut-count]');
                const percentage = item.querySelector('[data-annual-donut-percentage]');
                if (count) count.textContent = formatter.format(statistic.counts?.[key] || 0);
                if (percentage) percentage.textContent = `${Number(statistic.percentages?.[key] || 0).toFixed(1)}%`;
            });
        };

        yearSelect.addEventListener('change', () => render(yearSelect.value));
        render(yearSelect.value);
    });
};

document.addEventListener('DOMContentLoaded', initializeAnnualDonutCharts);
document.addEventListener('livewire:navigated', initializeAnnualDonutCharts);
