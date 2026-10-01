import ChatIndex, { type ChatIndexProps } from './chat';

/**
 * `/ai/{module}` is a thin wrapper of the general chat home: the same surface
 * and the same props, with `module` narrowing the thread rail and tagging the
 * new thread (which is what resolves the module's scope for its turns).
 *
 * `suggestions` arrives from the backend (three prompts per module, empty on
 * the general chat) and `ChatIndex` turns it into the module heading, the
 * three chips that fill the composer and the back link to `/ai/chat`.
 */
export default function ModuleChat(props: ChatIndexProps) {
    return <ChatIndex {...props} />;
}