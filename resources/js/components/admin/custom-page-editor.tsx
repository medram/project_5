import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

export function CustomPageEditor({
    value,
    onChange,
}: {
    value: string;
    onChange: (html: string) => void;
}) {
    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: {
                    openOnClick: false,
                    protocols: ['http', 'https', 'mailto'],
                },
            }),
        ],
        content: value,
        onUpdate: ({ editor: current }) => onChange(current.getHTML()),
        editorProps: {
            attributes: {
                class: 'min-h-64 rounded-b-md border-x border-b bg-background p-4 text-sm leading-7 outline-none',
            },
        },
    });

    function setLink(): void {
        const href = window.prompt('Safe link URL (https, http, or mailto)');

        if (!href) {
            return;
        }

        if (!/^(https?:\/\/|mailto:)/i.test(href)) {
            return;
        }

        editor?.chain().focus().extendMarkRange('link').setLink({ href }).run();
    }

    return (
        <div className="overflow-hidden rounded-md border">
            <div className="flex flex-wrap gap-1 border-b bg-muted/40 p-2">
                <ToolbarButton
                    label="Bold"
                    onClick={() => editor?.chain().focus().toggleBold().run()}
                >
                    <Bold aria-hidden="true" />
                </ToolbarButton>
                <ToolbarButton
                    label="Italic"
                    onClick={() => editor?.chain().focus().toggleItalic().run()}
                >
                    <Italic aria-hidden="true" />
                </ToolbarButton>
                <ToolbarButton
                    label="Heading 2"
                    onClick={() =>
                        editor
                            ?.chain()
                            .focus()
                            .toggleHeading({ level: 2 })
                            .run()
                    }
                >
                    H2
                </ToolbarButton>
                <ToolbarButton
                    label="Heading 3"
                    onClick={() =>
                        editor
                            ?.chain()
                            .focus()
                            .toggleHeading({ level: 3 })
                            .run()
                    }
                >
                    H3
                </ToolbarButton>
                <ToolbarButton
                    label="Bullet list"
                    onClick={() =>
                        editor?.chain().focus().toggleBulletList().run()
                    }
                >
                    <List aria-hidden="true" />
                </ToolbarButton>
                <ToolbarButton
                    label="Numbered list"
                    onClick={() =>
                        editor?.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrdered aria-hidden="true" />
                </ToolbarButton>
                <ToolbarButton
                    label="Blockquote"
                    onClick={() =>
                        editor?.chain().focus().toggleBlockquote().run()
                    }
                >
                    <Quote aria-hidden="true" />
                </ToolbarButton>
                <ToolbarButton
                    label="Code"
                    onClick={() => editor?.chain().focus().toggleCode().run()}
                >
                    <Code aria-hidden="true" />
                </ToolbarButton>
                <ToolbarButton label="Safe link" onClick={setLink}>
                    <Link2 aria-hidden="true" />
                </ToolbarButton>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}

function ToolbarButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Button
            type="button"
            size="sm"
            variant="ghost"
            className="min-h-9"
            aria-label={label}
            title={label}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}
