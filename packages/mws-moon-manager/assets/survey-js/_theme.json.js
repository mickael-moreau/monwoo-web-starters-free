// 🌖🌖 Copyright Monwoo 2024 🌖🌖, by Miguel Monwoo
// service@monwoo.com

// Biblio :
// https://surveyjs.io/survey-creator/examples/change-form-theme/reactjs#content-code
// https://surveyjs.io/survey-creator/examples/change-form-theme/jquery#content-code
// https://surveyjs.io/form-library/documentation/manage-default-themes-and-styles
// https://surveyjs.io/form-library/documentation/get-started-jquery#link-surveyjs-resources
// https://surveyjs.io/form-library/documentation/manage-default-themes-and-styles#switch-between-themes

const baseUrl = process.env.BASE_HREF;
// TODO : why no need to jsonParse ? auto done by webpack ?
// const baseUrlFull = JSON.parse(process.env.BASE_HREF_FULL ?? "null") ?? null;

export const surveyTheme = ({

}) => ({
  "cssVariables": {
      // "--sjs-general-backcolor": "rgba(255, 255, 255, 0.1)",
      // "--sjs-general-backcolor-dark": "rgba(52, 52, 52, 1)",
      // "--sjs-general-backcolor-dim": "#FFFFFF",
      // "--sjs-general-backcolor-dim-light": "rgba(76, 57, 140, 0.25)",
      // "--sjs-general-backcolor-dim-dark": "rgba(46, 46, 46, 1)",
      // "--sjs-general-forecolor": "rgba(255, 255, 255, 0.78)",
      // "--sjs-general-forecolor-light": "rgba(255, 255, 255, 0.42)",
      // "--sjs-general-dim-forecolor": "rgba(255, 255, 255, 0.79)",
      // "--sjs-general-dim-forecolor-light": "rgba(255, 255, 255, 0.45)",
      // "--sjs-primary-backcolor": "rgba(80, 61, 153, 1)",
      // "--sjs-primary-backcolor-light": "rgba(80, 61, 153, 0.1)",
      // "--sjs-primary-backcolor-dark": "rgba(96, 92, 177, 1)",
      // "--sjs-primary-forecolor": "rgba(255, 255, 255, 1)",
      // "--sjs-primary-forecolor-light": "rgba(255, 255, 255, 0.25)",
      // "--sjs-base-unit": "8px",
      // "--sjs-corner-radius": "4px",
      // "--sjs-secondary-backcolor": "rgba(255, 152, 20, 1)",
      // "--sjs-secondary-backcolor-light": "rgba(255, 152, 20, 0.1)",
      // "--sjs-secondary-backcolor-semi-light": "rgba(255, 152, 20, 0.25)",
      // "--sjs-secondary-forecolor": "rgba(48, 48, 48, 1)",
      // "--sjs-secondary-forecolor-light": "rgba(48, 48, 48, 0.25)",
      // "--sjs-shadow-small": "0px 0px 0px 1px rgba(255, 255, 255, 0.25)",
      // "--sjs-shadow-medium": "0px 2px 6px 0px rgba(0, 0, 0, 0.2)",
      // "--sjs-shadow-large": "0px 8px 16px 0px rgba(0, 0, 0, 0.2)",
      // "--sjs-shadow-inner": "0px 0px 0px 1px rgba(255, 255, 255, 0.15)",
      // "--sjs-border-light": "rgba(255, 255, 255, 0.08)",
      // "--sjs-border-default": "rgba(255, 255, 255, 0.12)",
      // "--sjs-border-inside": "rgba(255, 255, 255, 0.08)",
      // "--sjs-special-red": "rgba(254, 76, 108, 1)",
      // "--sjs-special-red-light": "rgba(254, 76, 108, 0.1)",
      // "--sjs-special-red-forecolor": "rgba(48, 48, 48, 1)",
      // "--sjs-special-green": "rgba(36, 197, 164, 1)",
      // "--sjs-special-green-light": "rgba(36, 197, 164, 0.1)",
      // "--sjs-special-green-forecolor": "rgba(48, 48, 48, 1)",
      // "--sjs-special-blue": "rgba(91, 151, 242, 1)",
      // "--sjs-special-blue-light": "rgba(91, 151, 242, 0.1)",
      // "--sjs-special-blue-forecolor": "rgba(48, 48, 48, 1)",
      // "--sjs-special-yellow": "rgba(255, 152, 20, 1)",
      // "--sjs-special-yellow-light": "rgba(255, 152, 20, 0.1)",
      // "--sjs-special-yellow-forecolor": "rgba(48, 48, 48, 1)",
      // "--sjs-question-background": "rgba(255, 255, 255, 1)",
      // "--sjs-questionpanel-backcolor": "rgba(255, 255, 255, 0.1)",
      // "--sjs-questionpanel-hovercolor": "rgba(255, 255, 255, 0.1)",
      // "--sjs-questionpanel-cornerRadius": "8px",
      // "--sjs-editor-background": "rgba(76, 57, 140, 1)",
      // "--sjs-editorpanel-backcolor": "rgba(76, 57, 140, 0.25)",
      // "--sjs-editorpanel-hovercolor": "rgba(76, 57, 140, 0.35)",
      // "--sjs-editorpanel-cornerRadius": "6px",
      // "--sjs-font-pagetitle-family": "Open Sans",
      // "--sjs-font-pagetitle-weight": "700",
      // "--sjs-font-pagetitle-color": "rgba(255, 255, 255, 1)",
      // "--sjs-font-pagetitle-size": "32px",
      // "--sjs-font-questiontitle-family": "Open Sans",
      // "--sjs-font-questiontitle-weight": "600",
      // "--sjs-font-questiontitle-color": "rgba(255, 255, 255, 1)",
      // "--sjs-font-questiontitle-size": "16px",
      // "--sjs-font-questiondescription-family": "Open Sans",
      // "--sjs-font-questiondescription-weight": "400",
      // "--sjs-font-questiondescription-color": "rgba(255, 255, 255, 0.5)",
      // "--sjs-font-questiondescription-size": "16px",
      // "--sjs-font-editorfont-family": "Open Sans",
      // "--sjs-font-editorfont-weight": "400",
      // "--sjs-font-editorfont-color": "rgba(255, 255, 255, 1)",
      // "--sjs-font-editorfont-size": "16px",
      // "--sjs-font-size": "16px",
      // "--sjs-article-font-x-large-fontSize": "20pt",
      // "--sjs-font-questiontitle-size": "17pt",
      // "--sjs-font-editorfont-size": "17pt",
      // "--sjs-font-size": "16pt",
      // "--font-size": 100,
      // "--font-family": "Open Sans",
  },
  "themeName": "default",
  "themePalette": "dark",
  // "backgroundImage": "https://picsum.photos/id/53/800/600",
  // "backgroundImage": `/${baseUrl}/assets/img/surveys/telepro/abstract-blue-water-sea-water-background-texture-pixabay.jpg`,
  // "backgroundImage": `${baseUrlFull}/assets/img/surveys/telepro/abstract-blue-water-sea-water-background-texture-pixabay.jpg`,
  // "backgroundImage": `linear-gradient(#7191b3, #446e9b 50%, #3f658f)`,
  "backgroundImageFit": "cover",
  "backgroundImageAttachment": "fixed",
  "isCompact": false,
  "backgroundOpacity": 1
});