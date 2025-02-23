export type LoadedDocumentData = {
	blankId: number;
	entityId: number;
	entityType: string;
	entityTypeId: number;
	id: number;
	initiator: string;
	langId: string;
	parties: number;
	resultFileId: number;
	scenario: string;
	status: string;
	title: string;
	uid: string;
	version: number;
};
export type Communication = {
	ID: number;
	TYPE: 'EMAIL' | 'PHONE';
	VALUE: string;
	VALUE_TYPE: string;
};
export type BlockData = {
	text?: string;
	field?: string;
	hasFields?: boolean;
	presetId?: number;
	__view?: { crmNumeratorUrl?: string; base64?: string; };
	fileId?: number;
};
type BlockSettings = {
	positon: {
		top: string;
		left: string;
		width: string;
		widthPx: number;
		height: string;
		heightPx: number;
	},
	style: { [$Keys<CSSStyleDeclaration>]: string; }
};
export type LoadedBlock = {
	code: string;
	data: BlockData;
	id: number;
	party: number;
	type: string;
	position: BlockSettings['position'];
	style: BlockSettings['style'];
};

export type Role = 'assignee' | 'signer' | 'editor' | 'reviewer';
export const MemberRole: $ReadOnly<{ [key: Role]: Role }> = Object.freeze({
	assignee: 'assignee',
	signer: 'signer',
	editor: 'editor',
	reviewer: 'reviewer',
});

export type SetupMember = {
	entityType: string;
	entityId: number;
	party: number;
	role?: Role;
};
