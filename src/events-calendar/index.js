/**
 * Month calendar for /events/.
 *
 * Wide: a month grid with each date's time and title. Narrow (under 768px of
 * available width, so a narrow column counts): days show a dot, and choosing a
 * day lists its dates below. Days can be chosen by touch, mouse, or keyboard.
 *
 * ChMS text is only ever written with textContent, never as HTML.
 */
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

import './index.scss';

const COMPACT_WIDTH = 768;
const WEEK = 7;

const element = ( tag, className, text ) => {
	const node = document.createElement( tag );
	node.className = className;

	if ( text ) {
		node.textContent = text;
	}

	return node;
};

/** The date part of a `Y-m-dTH:i:s` or `Y-m-d` string. */
const dayOf = ( value ) => String( value || '' ).substring( 0, 10 );

/** Move a `Y-m-d` date by a number of days. Parsed as UTC to avoid clock changes. */
const addDays = ( day, amount ) => {
	const date = new Date( `${ day }T00:00:00Z` );
	date.setUTCDate( date.getUTCDate() + amount );

	return date.toISOString().substring( 0, 10 );
};

function init( root, config ) {
	const grid = root.querySelector( '[data-cp-sync-grid]' );
	const status = root.querySelector( '[data-cp-sync-status]' );
	const panel = root.querySelector( '[data-cp-sync-day]' );
	const panelTitle = root.querySelector( '[data-cp-sync-day-title]' );
	const panelEmpty = root.querySelector( '[data-cp-sync-day-empty]' );
	const panelList = root.querySelector( '[data-cp-sync-day-list]' );
	const { i18n } = config;

	let eventsByDay = {};
	let selected = dayOf( config.now );
	let compact = false;
	let calendar = null;
	let firstLoad = true;

	const setStatus = ( message ) => {
		status.textContent = message || '';
		status.hidden = ! message;
	};

	const dayLabel = new Intl.DateTimeFormat( config.locale, {
		weekday: 'long',
		month: 'long',
		day: 'numeric',
		timeZone: 'UTC',
	} );

	const labelFor = ( day ) => dayLabel.format( new Date( `${ day }T00:00:00Z` ) );

	const renderPanel = () => {
		panel.hidden = ! compact;

		if ( ! compact ) {
			return;
		}

		const events = eventsByDay[ selected ] || [];

		panelTitle.textContent = labelFor( selected );
		panelEmpty.textContent = i18n.noDay;
		panelEmpty.hidden = events.length > 0;
		panelList.replaceChildren(
			...events.map( ( event ) => {
				const props = event.extendedProps;
				const item = element( 'li', 'cp-sync-calendar__day-item' );
				const link = element( 'a', 'cp-sync-calendar__day-link' );

				link.href = event.url;
				link.append( element( 'span', 'cp-sync-calendar__day-event', event.title ) );
				link.append( element( 'span', 'cp-sync-calendar__day-time', props.timeRange ) );

				if ( props.location ) {
					link.append( element( 'span', 'cp-sync-calendar__day-location', props.location ) );
				}

				if ( props.categories.length ) {
					link.append( element( 'span', 'cp-sync-calendar__day-terms', props.categories.join( ', ' ) ) );
				}

				item.append( link );

				return item;
			} )
		);
	};

	/** Dots, the selected day, and keyboard stops for the visible month. */
	const syncCells = () => {
		grid.querySelectorAll( '.fc-daygrid-day' ).forEach( ( cell ) => {
			const day = cell.dataset.date;
			const count = ( eventsByDay[ day ] || [] ).length;
			const frame = cell.querySelector( '.fc-daygrid-day-frame' );
			let dot = cell.querySelector( '.cp-sync-calendar__dot' );

			if ( count && ! dot && frame ) {
				dot = element( 'span', 'cp-sync-calendar__dot' );
				dot.setAttribute( 'aria-hidden', 'true' );
				frame.append( dot );
			} else if ( ! count && dot ) {
				dot.remove();
			}

			cell.classList.toggle( 'is-selected', compact && day === selected );

			if ( compact ) {
				const events = count ? ` ${ i18n.dayEvents.replace( '%d', count ) }` : '';

				cell.tabIndex = day === selected ? 0 : -1;
				cell.setAttribute( 'aria-label', `${ labelFor( day ) }.${ events }` );
				cell.setAttribute( 'aria-selected', day === selected ? 'true' : 'false' );
			} else {
				cell.removeAttribute( 'tabindex' );
				cell.removeAttribute( 'aria-label' );
				cell.removeAttribute( 'aria-selected' );
			}
		} );
	};

	const selectDay = ( day, focus = false ) => {
		selected = day;
		syncCells();
		renderPanel();

		if ( focus ) {
			const cell = grid.querySelector( `.fc-daygrid-day[data-date="${ day }"]` );

			if ( cell ) {
				cell.focus();
			}
		}
	};

	/** Keep the chosen day in the month on screen, preferring a day with events. */
	const keepSelectionInMonth = () => {
		const first = dayOf( calendar.view.currentStart.toISOString() );
		const end = dayOf( calendar.view.currentEnd.toISOString() );

		if ( selected >= first && selected < end ) {
			return;
		}

		const days = Object.keys( eventsByDay ).sort();
		const withEvents = days.find( ( day ) => day >= first && day < end );

		selected = withEvents || first;
	};

	const refresh = () => {
		keepSelectionInMonth();
		syncCells();
		renderPanel();
	};

	const indexByDay = ( events ) => {
		const map = {};

		events.forEach( ( event ) => {
			const last = event.extendedProps.lastDay || dayOf( event.start );

			for ( let day = dayOf( event.start ); day <= last; day = addDays( day, 1 ) ) {
				( map[ day ] = map[ day ] || [] ).push( event );
			}
		} );

		return map;
	};

	const fetchEvents = ( info, success, failure ) => {
		const params = new URLSearchParams( {
			start: dayOf( info.startStr ),
			end: dayOf( info.endStr ),
		} );

		if ( config.category ) {
			params.set( 'category', config.category );
		}

		const separator = config.restUrl.includes( '?' ) ? '&' : '?';

		setStatus( i18n.loading );

		window
			.fetch( `${ config.restUrl }${ separator }${ params }` )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}

				return response.json();
			} )
			.then( ( data ) => {
				const events = data.events || [];
				const first = dayOf( calendar.view.currentStart.toISOString() );
				const end = dayOf( calendar.view.currentEnd.toISOString() );

				eventsByDay = indexByDay( events );

				const inMonth = Object.keys( eventsByDay ).some( ( day ) => day >= first && day < end );

				// Open on today, or on the next day with events when today has none.
				if ( firstLoad && ! eventsByDay[ selected ] ) {
					const today = selected;
					const next = Object.keys( eventsByDay ).sort().find( ( day ) => day >= today && day < end );

					selected = next || today;
				}

				firstLoad = false;

				setStatus( inMonth ? '' : i18n.noMonth );
				success( events );
				refresh();
			} )
			.catch( ( error ) => {
				setStatus( i18n.error );
				failure( error );
			} );
	};

	const arrowStep = {
		ArrowLeft: -1,
		ArrowRight: 1,
		ArrowUp: -WEEK,
		ArrowDown: WEEK,
	};

	grid.addEventListener( 'keydown', ( event ) => {
		if ( ! compact || ! event.target.matches( '.fc-daygrid-day' ) ) {
			return;
		}

		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			selectDay( event.target.dataset.date, true );
		} else if ( arrowStep[ event.key ] ) {
			const next = addDays( event.target.dataset.date, arrowStep[ event.key ] );

			// Stay in the days on screen. The month buttons change the month.
			if ( grid.querySelector( `.fc-daygrid-day[data-date="${ next }"]` ) ) {
				event.preventDefault();
				selectDay( next, true );
			}
		}
	} );

	calendar = new Calendar( grid, {
		plugins: [ dayGridPlugin, interactionPlugin ],
		initialView: 'dayGridMonth',
		// Event times arrive as clock times with no offset. Reading them as UTC
		// keeps them as sent, whatever the visitor's timezone is.
		timeZone: 'UTC',
		now: config.now,
		firstDay: config.firstDay,
		locale: { code: config.locale, buttonText: { today: i18n.today } },
		buttonHints: { prev: i18n.prevMonth, next: i18n.nextMonth, today: i18n.today },
		headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
		height: 'auto',
		fixedWeekCount: false,
		dayMaxEvents: 4,
		eventDisplay: 'block',
		moreLinkContent: ( arg ) => i18n.more.replace( '%d', arg.num ),
		events: fetchEvents,
		eventContent: ( arg ) => {
			const nodes = [];

			// A date that runs into a new week shows its time only where it starts.
			if ( arg.isStart && arg.event.extendedProps.startTime ) {
				nodes.push( element( 'span', 'cp-sync-calendar__event-time', arg.event.extendedProps.startTime ) );
			}

			nodes.push( element( 'span', 'cp-sync-calendar__event-title', arg.event.title ) );

			return { domNodes: nodes };
		},
		dateClick: ( info ) => selectDay( dayOf( info.dateStr ) ),
		datesSet: () => refresh(),
	} );

	const applyWidth = () => {
		const next = root.clientWidth < COMPACT_WIDTH;

		if ( next !== compact ) {
			compact = next;
			root.classList.toggle( 'is-compact', compact );
			calendar.updateSize();
			refresh();
		}
	};

	calendar.render();
	applyWidth();

	if ( window.ResizeObserver ) {
		new window.ResizeObserver( applyWidth ).observe( root );
	}
}

document.querySelectorAll( '[data-cp-sync-calendar]' ).forEach( ( root ) => {
	if ( window.cpSyncCalendar ) {
		init( root, window.cpSyncCalendar );
	}
} );
