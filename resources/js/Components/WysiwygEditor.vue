<template>
    <div class="wysiwyg rounded-md border border-input bg-background">
        <div ref="el"></div>
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const props = defineProps({ modelValue: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);

const el = ref(null);
let quill = null;
let internal = false;

onMounted(() => {
    quill = new Quill(el.value, {
        theme: 'snow',
        placeholder: 'İçeriği buraya yazın…',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link'],
                ['clean'],
            ],
        },
    });

    if (props.modelValue) {
        quill.clipboard.dangerouslyPasteHTML(props.modelValue);
    }

    quill.on('text-change', () => {
        internal = true;
        const html = quill.root.innerHTML;
        emit('update:modelValue', html === '<p><br></p>' ? '' : html);
        internal = false;
    });
});

watch(() => props.modelValue, (val) => {
    if (internal || !quill) return;
    if ((val || '') !== quill.root.innerHTML) {
        quill.clipboard.dangerouslyPasteHTML(val || '');
    }
});

onBeforeUnmount(() => {
    quill = null;
});
</script>
