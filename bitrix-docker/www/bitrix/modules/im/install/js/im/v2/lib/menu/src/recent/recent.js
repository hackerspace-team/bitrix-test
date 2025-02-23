import { Loc, Type } from 'main.core';
import { EventEmitter } from 'main.core.events';
import { MessageBox, MessageBoxButtons } from 'ui.dialogs.messagebox';

import { Core } from 'im.v2.application.core';
import { ChatActionType, EventType, SidebarDetailBlock, UserRole, ChatType } from 'im.v2.const';
import { CallManager } from 'im.v2.lib.call';
import { ChatService, RecentService } from 'im.v2.provider.service';
import { Utils } from 'im.v2.lib.utils';
import { PermissionManager } from 'im.v2.lib.permission';
import { showLeaveFromChatConfirm } from 'im.v2.lib.confirm';
import { ChannelManager } from 'im.v2.lib.channel';
import { Messenger } from 'im.public';

import { BaseMenu } from '../base/base';
import { InviteManager } from './invite-manager';

import type { MenuItem } from 'im.v2.lib.menu';
import type { ImModelRecentItem, ImModelUser, ImModelChat } from 'im.v2.model';

export class RecentMenu extends BaseMenu
{
	context: ImModelRecentItem;
	callManager: CallManager;
	permissionManager: PermissionManager;
	chatService: ChatService;

	constructor()
	{
		super();

		this.id = 'im-recent-context-menu';
		this.chatService = new ChatService();
		this.callManager = CallManager.getInstance();
		this.permissionManager = PermissionManager.getInstance();
	}

	getMenuOptions(): Object
	{
		return {
			...super.getMenuOptions(),
			className: this.getMenuClassName(),
			angle: true,
			offsetLeft: 32,
		};
	}

	getMenuClassName(): string
	{
		return this.context.compactMode ? '' : super.getMenuClassName();
	}

	getMenuItems(): MenuItem[]
	{
		if (this.context.invitation.isActive)
		{
			return this.getInviteItems();
		}

		return [
			this.getOpenItem(),
			this.getUnreadMessageItem(),
			this.getPinMessageItem(),
			this.getMuteItem(),
			this.getCallItem(),
			this.getOpenProfileItem(),
			this.getChatsWithUserItem(),
			this.getHideItem(),
			this.getLeaveItem(),
		];
	}

	getSendMessageItem(): MenuItem
	{
		return {
			text: Loc.getMessage('IM_LIB_MENU_WRITE_V2'),
			onclick: () => {
				Messenger.openChat(this.context.dialogId);
				this.menuInstance.close();
			},
		};
	}

	getOpenItem(): MenuItem
	{
		return {
			text: Loc.getMessage('IM_LIB_MENU_OPEN'),
			onclick: () => {
				Messenger.openChat(this.context.dialogId);
				this.menuInstance.close();
			},
		};
	}

	getUnreadMessageItem(): ?MenuItem
	{
		if (this.isChannel())
		{
			return null;
		}

		const dialog = this.store.getters['chats/get'](this.context.dialogId, true);
		const showReadOption = this.context.unread || dialog.counter > 0;

		return {
			text: showReadOption ? Loc.getMessage('IM_LIB_MENU_READ') : Loc.getMessage('IM_LIB_MENU_UNREAD'),
			onclick: () => {
				if (showReadOption)
				{
					this.chatService.readDialog(this.context.dialogId);
				}
				else
				{
					this.chatService.unreadDialog(this.context.dialogId);
				}
				this.menuInstance.close();
			},
		};
	}

	getPinMessageItem(): ?MenuItem
	{
		const isPinned = this.context.pinned;

		return {
			text: isPinned ? Loc.getMessage('IM_LIB_MENU_UNPIN') : Loc.getMessage('IM_LIB_MENU_PIN'),
			onclick: () => {
				if (isPinned)
				{
					this.chatService.unpinChat(this.context.dialogId);
				}
				else
				{
					this.chatService.pinChat(this.context.dialogId);
				}
				this.menuInstance.close();
			},
		};
	}

	getMuteItem(): ?MenuItem
	{
		const canMute = this.permissionManager.canPerformAction(ChatActionType.mute, this.context.dialogId);
		if (!canMute)
		{
			return null;
		}

		const dialog = this.store.getters['chats/get'](this.context.dialogId, true);
		const isMuted = dialog.muteList.includes(Core.getUserId());

		return {
			text: isMuted ? Loc.getMessage('IM_LIB_MENU_UNMUTE_2') : Loc.getMessage('IM_LIB_MENU_MUTE_2'),
			onclick: () => {
				if (isMuted)
				{
					this.chatService.unmuteChat(this.context.dialogId);
				}
				else
				{
					this.chatService.muteChat(this.context.dialogId);
				}
				this.menuInstance.close();
			},
		};
	}

	getCallItem(): ?MenuItem
	{
		const chatCanBeCalled = this.callManager.chatCanBeCalled(this.context.dialogId);
		const chatIsAllowedToCall = this.permissionManager.canPerformAction(ChatActionType.call, this.context.dialogId);
		if (!chatCanBeCalled || !chatIsAllowedToCall)
		{
			return null;
		}

		return {
			text: Loc.getMessage('IM_LIB_MENU_CALL_2'),
			onclick: () => {
				this.callManager.startCall(this.context.dialogId);
				this.menuInstance.close();
			},
		};
	}

	getOpenProfileItem(): ?MenuItem
	{
		if (!this.isUser() || this.isBot())
		{
			return null;
		}

		const profileUri = Utils.user.getProfileLink(this.context.dialogId);

		return {
			text: Loc.getMessage('IM_LIB_MENU_OPEN_PROFILE_V2'),
			href: profileUri,
			onclick: () => {
				this.menuInstance.close();
			},
		};
	}

	getHideItem(): ?MenuItem
	{
		if (this.context.invitation?.isActive || this.context.options?.default_user_record)
		{
			return null;
		}

		return {
			text: Loc.getMessage('IM_LIB_MENU_HIDE'),
			onclick: () => {
				RecentService.getInstance().hideChat(this.context.dialogId);

				this.menuInstance.close();
			},
		};
	}

	getLeaveItem(): ?MenuItem
	{
		const canLeaveChat = this.permissionManager.canPerformAction(ChatActionType.leave, this.context.dialogId);
		if (!canLeaveChat)
		{
			return null;
		}

		return {
			text: Loc.getMessage('IM_LIB_MENU_LEAVE_V2'),
			onclick: async () => {
				this.menuInstance.close();
				const userChoice = await showLeaveFromChatConfirm();
				if (userChoice === true)
				{
					this.chatService.leaveChat(this.context.dialogId);
				}
			},
		};
	}

	getChatsWithUserItem(): ?MenuItem
	{
		if (!this.isUser() || this.isBot())
		{
			return null;
		}

		const isAnyChatOpened = this.store.getters['application/getLayout'].entityId.length > 0;

		return {
			text: Loc.getMessage('IM_LIB_MENU_FIND_CHATS_WITH_USER'),
			onclick: async () => {
				if (!isAnyChatOpened)
				{
					await Messenger.openChat(this.context.dialogId);
				}

				EventEmitter.emit(EventType.sidebar.open, {
					panel: SidebarDetailBlock.chatsWithUser,
					standalone: true,
					dialogId: this.context.dialogId,
				});
				this.menuInstance.close();
			},
		};
	}

	// invitation
	getInviteItems(): Array
	{
		const items = [
			this.getSendMessageItem(),
			this.getOpenProfileItem(),
		];

		let canInvite; // TODO change to APPLICATION variable
		if (Type.isUndefined(BX.MessengerProxy))
		{
			canInvite = true;
			console.error('BX.MessengerProxy.canInvite() method not found in v2 version!');
		}
		else
		{
			canInvite = BX.MessengerProxy.canInvite();
		}

		const canManageInvite = canInvite && Core.getUserId() === this.context.invitation.originator;
		if (canManageInvite)
		{
			items.push(
				this.getDelimiter(),
				this.context.invitation.canResend ? this.getResendInviteItem() : null,
				this.getCancelInviteItem(),
			);
		}

		return items;
	}

	getResendInviteItem(): MenuItem
	{
		return {
			text: Loc.getMessage('IM_LIB_INVITE_RESEND'),
			onclick: () => {
				InviteManager.resendInvite(this.context.dialogId);
				this.menuInstance.close();
			},
		};
	}

	getCancelInviteItem(): MenuItem
	{
		return {
			text: Loc.getMessage('IM_LIB_INVITE_CANCEL'),
			onclick: () => {
				MessageBox.show({
					message: Loc.getMessage('IM_LIB_INVITE_CANCEL_CONFIRM'),
					modal: true,
					buttons: MessageBoxButtons.OK_CANCEL,
					onOk: (messageBox) => {
						InviteManager.cancelInvite(this.context.dialogId);
						messageBox.close();
					},
					onCancel: (messageBox) => {
						messageBox.close();
					},
				});
				this.menuInstance.close();
			},
		};
	}
	// invitation end

	getDelimiter(): Object
	{
		return { delimiter: true };
	}

	isUser(): boolean
	{
		return this.store.getters['chats/isUser'](this.context.dialogId);
	}

	isBot(): boolean
	{
		if (!this.isUser())
		{
			return false;
		}

		const user: ImModelUser = this.store.getters['users/get'](this.context.dialogId);

		return user.bot === true;
	}

	isChannel(): boolean
	{
		return ChannelManager.isChannel(this.context.dialogId);
	}

	isCommentsChat(): boolean
	{
		const { type }: ImModelChat = this.store.getters['chats/get'](this.context.dialogId, true);

		return type === ChatType.comment;
	}
}
