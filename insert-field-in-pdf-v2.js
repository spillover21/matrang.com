import '../konva/skia-backend.js';
import Konva from 'konva';
import path from 'node:path';
import { FontLibrary } from 'skia-canvas';
import { renderField } from '../../universal/field-renderer/render-field.js';

// sort-imports-ignore
const insertFieldInPDFV2 = async ({
  pageWidth,
  pageHeight,
  fields
}) => {
  const fontPath = path.join(process.cwd(), 'public/fonts');
  // eslint-disable-next-line react-hooks/rules-of-hooks
  FontLibrary.use({
    ['Caveat']: [path.join(fontPath, 'caveat.ttf')],
    ['Noto Sans']: [path.join(fontPath, 'noto-sans.ttf')],
    ['Noto Sans Japanese']: [path.join(fontPath, 'noto-sans-japanese.ttf')],
    ['Noto Sans Chinese']: [path.join(fontPath, 'noto-sans-chinese.ttf')],
    ['Noto Sans Korean']: [path.join(fontPath, 'noto-sans-korean.ttf')]
  });
  let stage = new Konva.Stage({
    width: pageWidth,
    height: pageHeight
  });
  let layer = new Konva.Layer();
  // Render the fields onto the layer.
  for (const field of fields) {
    renderField({
      scale: 1,
      field: {
        renderId: field.id.toString(),
        ...field,
        width: Number(field.width),
        height: Number(field.height),
        positionX: (field.type === 'SIGNATURE' && Number(field.positionX) < 35) ? Number(field.positionX) + 50 : Number(field.positionX),
        positionY: Number(field.positionY)
      },
      translations: null,
      pageLayer: layer,
      pageWidth,
      pageHeight,
      mode: 'export'
    });
  }
  stage.add(layer);
  // eslint-disable-next-line @typescript-eslint/consistent-type-assertions
  const canvas = layer.canvas._canvas;
  // Embed the SVG into the PDF
  const pdf = await canvas.toBuffer('pdf');
  stage.destroy();
  layer.destroy();
  stage = null;
  layer = null;
  return pdf;
};

export { insertFieldInPDFV2 };
//# sourceMappingURL=insert-field-in-pdf-v2.js.map
