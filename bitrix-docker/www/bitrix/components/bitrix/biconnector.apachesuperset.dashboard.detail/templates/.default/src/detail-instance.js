import { Dom, Event, Loc, Type } from 'main.core';
import { EventEmitter } from 'main.core.events';
import type { MenuItemOptions } from 'main.popup';
import { Menu } from 'main.popup';
import type { DetailConfig } from './type/detail-config';
import type { DashboardEmbeddedParameters } from './type/dashboard-embedded-parameters';
import { DashboardManager } from 'biconnector.apache-superset-dashboard-manager';
import { ApacheSupersetEmbeddedLoader } from 'biconnector.apache-superset-embedded-loader';
import { ApacheSupersetAnalytics } from 'biconnector.apache-superset-analytics';
import { ApacheSupersetFeedbackForm } from 'biconnector.apache-superset-feedback-form';
import 'sidepanel';

export class DetailInstance
{
	#dashboardManager: DashboardManager;
	#dashboardNode: HTMLElement;
	#frameNode: HTMLElement;
	#editBtn: HTMLElement;

	#embeddedParams: DashboardEmbeddedParameters;
	#embeddedLoader: ApacheSupersetEmbeddedLoader;
	#canExport: boolean;
	#canEdit: boolean;

	#moreMenu: Menu;

	constructor(config: DetailConfig)
	{
		this.#dashboardNode = document.getElementById(config.appNodeId);
		if (!Type.isDomNode(this.#dashboardNode))
		{
			const errorMsg = `Cannot init superset dashboard. Node with ID ${config.appNodeId} does not exists`;
			throw new Error(errorMsg);
		}
		this.#dashboardManager = new DashboardManager();
		this.#canExport = config.canExport === 'Y';
		this.#canEdit = config.canEdit === 'Y';
		this.#embeddedParams = config.dashboardEmbeddedParams;

		this.#frameNode = this.#dashboardNode.querySelector('.dashboard-iframe');
		this.#subscribeEvents();
		this.#initHeaderButtons();

		if (!BX.BIConnector.LimitLockPopup)
		{
			this.#initFrame(this.#embeddedParams);
		}
	}

	#subscribeEvents()
	{
		EventEmitter.subscribe('BiConnector:DashboardSelector.onSelect', (event) => {
			Dom.clean(this.#frameNode);
			BX.BIConnector.ApacheSuperset.Dashboard.Detail.createSkeleton({
				container: this.#frameNode,
				isSupersetAvailable: true,
			});
		});

		EventEmitter.subscribe('BiConnector:DashboardSelector.onSelectDataLoaded', (event) => {
			this.#embeddedParams = event.data.credentials;
			Dom.clean(this.#frameNode);
			this.#canEdit = event.data.credentials.canEdit === 'Y';
			this.#canExport = event.data.credentials.canExport === 'Y';
			this.#embeddedParams = event.data.credentials;
			top.window.history.pushState(null, '', event.data.credentials.dashboardUrl);

			this.#initFrame(event.data.credentials);
			this.#initHeaderButtons();
		});

		EventEmitter.subscribe('BiConnector:LimitPopup.Warning.onClose', (event) => {
			this.#initFrame(this.#embeddedParams);
			this.#initHeaderButtons();
		});

		EventEmitter.subscribe('BX.BIConnector.Settings:onAfterSave', () => {
			this.#reloadGridAfterSliderClose();
		});
	}

	#initFrame(embeddedParams: DashboardEmbeddedParameters)
	{
		const dashboardParams = {
			id: embeddedParams.uuid, // given by the Superset embedding UI
			supersetDomain: embeddedParams.supersetDomain,
			mountPoint: this.#frameNode, // any html element that can contain an iframe
			fetchGuestToken: embeddedParams.guestToken,
			debug: true,
			dashboardUiConfig: { // dashboard UI config: hideTitle, hideTab, ...etc.
				hideTitle: true,
				hideTab: true,
				hideChartControls: true,
				filters: {
					expanded: true,
					visible: true,
					nativeFilters: embeddedParams.nativeFilters,
				},
			},
		};

		this.#embeddedLoader = new ApacheSupersetEmbeddedLoader(dashboardParams);
		this.#embeddedLoader.embedDashboard();
	}

	#initHeaderButtons()
	{
		this.#initMoreMenu();

		this.#editBtn = this.#dashboardNode.querySelector('.dashboard-header-buttons-edit');
		Event.unbindAll(this.#editBtn);

		if (this.#canEdit)
		{
			this.#enableEditButton();
			Event.bind(this.#editBtn, 'click', this.#onEditButtonClick.bind(this));
		}
		else
		{
			this.#disableEditButton();
			Event.unbindAll(this.#editBtn);
		}

		if (BX.BIConnector.LimitLockPopup)
		{
			this.#disableEditButton();
			Event.unbindAll(this.#editBtn);
		}
	}

	#onEditButtonClick()
	{
		this.#muteEditButton();

		const dashboardInfo = {
			id: this.#embeddedParams.id,
			editLink: this.#embeddedParams.editUrl,
			type: this.#embeddedParams.type,
		};

		this.#dashboardManager.processEditDashboard(
			dashboardInfo,
			() => {
				this.#unmuteEditButton();
			},
			(popupType) => {
				ApacheSupersetAnalytics.sendAnalytics('edit', 'report_edit', {
					c_sub_section: popupType,
					c_element: 'detail_button',
					type: this.#embeddedParams.type.toLowerCase(),
					p1: ApacheSupersetAnalytics.buildAppIdForAnalyticRequest(this.#embeddedParams.appId),
					p2: this.#embeddedParams.id,
					status: 'success',
				});
			},
			(popupType) => {
				ApacheSupersetAnalytics.sendAnalytics('edit', 'report_edit', {
					c_sub_section: popupType,
					c_element: 'detail_button',
					type: this.#embeddedParams.type.toLowerCase(),
					p1: ApacheSupersetAnalytics.buildAppIdForAnalyticRequest(this.#embeddedParams.appId),
					p2: this.#embeddedParams.id,
					status: 'error',
				});
			},
		);

		this.#reloadGridAfterSliderClose();
	}

	#reloadGridAfterSliderClose()
	{
		const slider = BX.SidePanel.Instance.getSliderByWindow(window);
		if (slider)
		{
			EventEmitter.subscribeOnce(slider, 'SidePanel.Slider:onClose', () => {
				if (!top.BX.Main || !top.BX.Main.gridManager)
				{
					return;
				}

				top.BX.Main.gridManager.data.forEach((grid) => {
					if (grid.instance.getId() === 'biconnector_superset_dashboard_grid')
					{
						grid.instance.reload();
					}
				});
			});
		}
	}

	#muteEditButton()
	{
		this.#disableEditButton();
		Dom.addClass(this.#editBtn, 'ui-btn-wait');
	}

	#unmuteEditButton()
	{
		this.#enableEditButton();
		Dom.removeClass(this.#editBtn, 'ui-btn-wait');
	}

	#disableEditButton()
	{
		this.#editBtn.setAttribute('disabled', 'true');
	}

	#enableEditButton()
	{
		this.#editBtn.removeAttribute('disabled');
	}

	#initMoreMenu()
	{
		const moreButton = this.#dashboardNode.querySelector('.dashboard-header-buttons-more');
		if (this.#moreMenu)
		{
			Event.unbindAll(moreButton);
		}

		this.#moreMenu = new Menu({
			closeByEsc: false,
			closeIcon: false,
			cacheable: true,
			angle: 'top',
			items: this.#getMoreMenuItems(),
			toFrontOnShow: true,
			autoHide: true,
			bindElement: moreButton,
			className: 'more-popup',
			events: {
				onBeforeClose: () => {
					this.#moreMenu.getMenuItems().forEach((menuItem) => {
						menuItem.closeSubMenu();
					});
				},
				onAfterShow: () => {
					const popupContainer = this.#getMoreMenu().getPopupWindow().getPopupContainer();
					const overHeight = popupContainer.getBoundingClientRect().top + popupContainer.offsetHeight;

					if (overHeight > window.innerHeight)
					{
						window.scrollTo({
							top: window.scrollY + (-window.innerHeight + overHeight),
							behavior: 'smooth',
						});
					}
				},
			},
		});

		Event.bind(moreButton, 'click', () => this.#moreMenu.show());
	}

	#getMoreMenuItems(): MenuItemOptions[]
	{
		const result = [
			{
				id: 'order_dashboard',
				text: Loc.getMessage('SUPERSET_DASHBOARD_DETAIL_MORE_MENU_ORDER_DASHBOARD'),
				title: Loc.getMessage('SUPERSET_DASHBOARD_DETAIL_MORE_MENU_ORDER_DASHBOARD'),
				onclick: () => {
					ApacheSupersetFeedbackForm.requestIntegrationFormOpen();
				},
			},
			{
				id: 'feedback',
				text: Loc.getMessage('SUPERSET_DASHBOARD_DETAIL_MORE_MENU_FEEDBACK'),
				title: Loc.getMessage('SUPERSET_DASHBOARD_DETAIL_MORE_MENU_FEEDBACK'),
				onclick: () => {
					ApacheSupersetFeedbackForm.feedbackFormOpen();
				},
			},
		];

		if (this.#canExport)
		{
			result.push({
				id: 'export',
				text: Loc.getMessage('SUPERSET_DASHBOARD_DETAIL_MORE_MENU_EXPORT'),
				title: Loc.getMessage('SUPERSET_DASHBOARD_DETAIL_MORE_MENU_EXPORT'),
				onclick: () => {
					this.#moreMenu.getMenuItem('export').disable();
					this.#dashboardManager.exportDashboard(this.#embeddedParams.id, 'detail_button')
						.finally(() => {
							this.#moreMenu.getMenuItem('export').enable();
						})
						.catch(() => {})
					;
				},
			});
		}

		return result;
	}

	#getMoreMenu(): Menu
	{
		return this.#moreMenu;
	}
}
