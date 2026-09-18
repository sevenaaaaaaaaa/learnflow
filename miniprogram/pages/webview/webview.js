Page({
  data: { url: '' },
  onLoad(q) { this.setData({ url: q.url ? decodeURIComponent(q.url) : '' }); }
});
