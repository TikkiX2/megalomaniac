import ChatIndex, { type ChatIndexProps } from './chat';

/**
 * `/ai/{module}` is a thin wrapper of the general chat home: the same surface
 * and the same props, with `module` narrowing the thread rail and tagging the
 * new thread (which is what resolves the module's scope for its turns).
 * Per-module titles and empty-state suggestions are Task 14's business.
 */
export default function ModuleChat(props: ChatIndexProps) {
    return <ChatIndex {...props} />;
}
